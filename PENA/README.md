# PENA — administração de conteúdo da Veneza Piscinas

Aplicação Laravel 13 em desenvolvimento para administrar o acervo do PENA. O painel começa em `/admin`; a leitura pública dos artigos usa `/api/public/posts` e `/api/public/posts/{id}`. Este diretório **não é publicado pelo GitHub Pages** da landing page. Não há deploy PHP configurado.

## Estado desta entrega

- Estrutura Laravel e Docker local, tela de login, sessão, logout e proteção de `/admin` implementados. Login usa a tabela nova `pena_admin_users`, cuja migração **não foi executada**.
- API pública de leitura preparada para as tabelas legadas de `../scripts/export-posts.sql`: filtra `STATUS_POST = PP`, devolve o contrato esperado pelo site institucional e não expõe rascunhos. A consulta foi testada com esquema sintético em SQLite; **não foi validada na base real**.
- Nenhum dado da base existente foi alterado. Não foi produzido um backup físico porque o servidor MySQL remoto não oferece TLS e ainda não há acesso seguro à hospedagem para exportação.
- Ainda não há CRUD de usuários, autores, mídias ou posts, nem reordenação editorial. Não existe administrador real cadastrado. O site institucional continua usando o snapshot local enquanto `apiBaseUrl` estiver vazio.

## Rodar localmente sem banco

O Docker deve estar ativo. Em um clone novo, na pasta `PENA`:

```powershell
docker run --rm -v "${PWD}:/app" -w /app composer:2 composer install
Copy-Item .env.example .env
docker run --rm -v "${PWD}:/app" -w /app composer:2 php artisan key:generate
docker compose up --build -d
```

Abra `http://127.0.0.1:8008/admin/login`. A página abre, mas ninguém pode entrar até a etapa de backup/migração/conta inicial. Sem banco configurado, a API responde `503` intencionalmente. Não use `composer setup` de versões antigas do scaffold: esta versão removeu a migração automática, mas qualquer migração manual continua proibida antes do backup.

Para testar sem tocar na base de produção:

```powershell
docker run --rm -v "${PWD}:/app" -w /app composer:2 php artisan test
```

Os testes usam SQLite em memória e dados fictícios. O `.env` local não contém as credenciais de produção e é ignorado pelo Git. Nunca versione senhas, dumps ou arquivos `.env`.

## Portão obrigatório antes de qualquer escrita na base existente

1. Obter da hospedagem um dump completo por canal seguro (por exemplo, exportação pelo painel HTTPS ou dump gerado no servidor e transferido por SFTP/SSH). Solicitar também uma forma segura de acessar o MySQL: TLS habilitado ou túnel SSH. A conexão TCP atual aceita login, mas o servidor informa que **não suporta TLS**; por isso não faremos dump nem migração por essa conexão.
2. Guardar o dump fora do repositório, em `C:\dev\veneza-backups` ou outro diretório privado, verificar o SHA-256 e testar restauração em banco isolado. O backup deve conter estrutura e dados; confirmar também triggers, rotinas e eventos, se existirem.
3. Inspecionar o esquema real e comparar tabelas, campos, índices, estados editoriais e relacionamentos com o adaptador em `app/Repositories/LegacyPostRepository.php`. Confirmar se `pena_admin_users` já existe antes de aplicar a migração proposta. Não modificar tabelas legadas às cegas.
4. Só então configurar `.env` com acesso seguro, `APP_DEBUG=false` fora do ambiente local, aplicar migrações revisadas e criar a conta inicial. O comando `php artisan pena:create-admin --backup=<caminho> --sha256=<hash>` solicita nome, e-mail e senha sem mostrá-la no terminal e exige um dump físico correspondente ao hash. A migração deve ser revisada e executada separadamente após o mesmo portão.

Sem acesso SSH/painel, peça ao provedor um dump por link HTTPS temporário autenticado e habilitação de TLS no MySQL, ou acesso SSH temporário para túnel. Não envie o dump por e-mail sem proteção e não faça a exportação por MySQL sem criptografia.

## Integração do site institucional

Após hospedar o PENA em origem HTTPS, adicionar a origem exata do site em `PUBLIC_SITE_ORIGINS` (lista separada por vírgulas) e definir `apiBaseUrl` em `site/assets/js/config.js`. A API retorna `{ "data": [...] }` ou `{ "data": {...} }`. Quando configurada, a interface não volta ao snapshot em caso de erro, `404` ou `410`, para não ressuscitar artigos retirados. A listagem atual retorna o HTML completo dos artigos; avaliar paginação e redução de payload antes da publicação definitiva.

## Pendências para concluir o produto

- Backup e transporte seguro do banco; validação do esquema real e dos estados `PP`/`PO`/`PE`.
- CRUD autenticado com autorização por papel para usuários, autores, mídias e posts; upload seguro, validação de HTML e trilha de auditoria.
- Ordenação editorial persistente sem perder a ordem histórica; migrations aditivas revisadas após conhecer o esquema.
- Testes de integração com cópia restaurada da base real; criação da conta inicial; HTTPS, domínio, CORS e hospedagem PHP.
- Ativar API no site, validar publicação/despublicação, artigos antigos, 404, falha da API e metadados SEO.
