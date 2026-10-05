# PENA — administração de conteúdo da Veneza Piscinas

Aplicação Laravel 13 em desenvolvimento para administrar o acervo do PENA. O painel começa em `/admin`; a leitura pública dos artigos usa `/api/public/posts` e `/api/public/posts/{id}`. Este diretório **não é publicado pelo GitHub Pages** da landing page. Não há deploy PHP configurado.

A hospedagem planejada é a ServHost, que disponibiliza cPanel, SSH por chave, SSL, Git Version Control, Application Manager, MySQL e seleção de PHP 8.3/8.4. Isso torna o deploy viável, mas ainda é necessário confirmar extensões, Composer, document root e acesso seguro ao banco. Não reutilize a versão PHP 7.4 herdada por alguns domínios sem configurar o subdomínio correto.

## Estado desta entrega

- Estrutura Laravel e Docker local, tela de login, sessão, logout e proteção de `/admin` implementados. Há cadastro autenticado de outros usuários administrativos em `/admin/users`. Login usa a tabela nova `pena_admin_users`, cuja migração **não foi executada**.
- API pública de leitura preparada para as tabelas legadas de `../scripts/export-posts.sql`: filtra `STATUS_POST = PP`, devolve o contrato esperado pelo site institucional e não expõe rascunhos. A ordem editorial pode ser modificada em `/admin/posts/order` e armazenada na tabela auxiliar `pena_post_order`, que também **não foi criada na base real**. A consulta e a ordenação foram testadas com esquema sintético em SQLite; **não foram validadas na base real**.
- Nenhum dado da base existente foi alterado. Não foi produzido um backup físico porque o servidor MySQL remoto não oferece TLS e ainda não há acesso seguro à hospedagem para exportação.
- Ainda não há edição/desativação de usuários nem CRUD de autores, mídias ou posts. Não existe administrador real cadastrado. O site institucional continua usando o snapshot local enquanto `apiBaseUrl` estiver vazio.

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
3. Inspecionar o esquema real e comparar tabelas, campos, índices, estados editoriais e relacionamentos com o adaptador em `app/Repositories/LegacyPostRepository.php`. Confirmar se `pena_admin_users` ou `pena_post_order` já existem antes de aplicar as migrações propostas. Não modificar tabelas legadas às cegas.
4. Só então configurar `.env` com acesso seguro, `APP_DEBUG=false` fora do ambiente local, aplicar migrações revisadas e criar a conta inicial. O comando `php artisan pena:create-admin --backup=<caminho> --sha256=<hash>` solicita nome, e-mail e senha sem mostrá-la no terminal e exige um arquivo de backup não vazio com o hash informado; a restauração deve ter sido verificada manualmente antes. Dentro do Docker, monte o diretório do backup somente para leitura, por exemplo `-v "C:\dev\veneza-backups:/backups:ro"`, e use o caminho `/backups/<arquivo.sql>` no comando. As migrações devem ser revisadas e executadas separadamente após o mesmo portão.

Sem acesso SSH/painel, peça ao provedor um dump por link HTTPS temporário autenticado e habilitação de TLS no MySQL, ou acesso SSH temporário para túnel. Não envie o dump por e-mail sem proteção e não faça a exportação por MySQL sem criptografia.

## Integração do site institucional

Após hospedar o PENA em origem HTTPS, adicionar a origem exata do site em `PUBLIC_SITE_ORIGINS` (lista separada por vírgulas) e definir `apiBaseUrl` em `site/assets/js/config.js`. A API retorna `{ "data": [...] }` ou `{ "data": {...} }`. Quando configurada, a interface não volta ao snapshot em caso de erro, `404` ou `410`, para não ressuscitar artigos retirados. A listagem atual retorna o HTML completo dos artigos; avaliar paginação e redução de payload antes da publicação definitiva.

## Pendências para concluir o produto

- Backup e transporte seguro do banco; validação do esquema real e dos estados `PP`/`PO`/`PE`.
- Completar edição/desativação de usuários e CRUD autenticado de autores, mídias e posts, com autorização por papel, upload seguro, validação de HTML e trilha de auditoria.
- Revisar a tabela de ordem editorial e as migrações aditivas contra o esquema real antes de instalá-las; testar reordenação e reversão em cópia restaurada sem perder a ordem histórica.
- Testes de integração com cópia restaurada da base real; criação da conta inicial; HTTPS, domínio, CORS e hospedagem PHP.
- Ativar API no site, validar publicação/despublicação, artigos antigos, 404, falha da API e metadados SEO.
