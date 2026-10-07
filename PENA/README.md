# PENA — administração de conteúdo da Veneza Piscinas

Aplicação Laravel 13 em desenvolvimento para administrar o acervo do PENA. O painel começa em `/admin`; a leitura pública dos artigos usa `/api/public/posts` e `/api/public/posts/{id}`. Este diretório **não é publicado pelo GitHub Pages** da landing page. Não há deploy PHP configurado.

O painel e a documentação interativa usam identidade roxa local: tokens em `public/admin.css`, extensões de tela nos demais `admin-*.css` e overrides do Swagger em `public/api-docs.css`. Estados de erro, sucesso, aviso e cores dos métodos HTTP permanecem distintos. A landing e o site institucional não foram alterados por esse tema.

A hospedagem planejada é a ServHost com cPanel. Em 05/10/2026, o suporte (#091143) confirmou bloqueio do SSH externo e liberou o Terminal web do cPanel, já utilizado pelo usuário. O **lock atual exige PHP >= 8.4.1** por suas dependências Symfony. O Terminal possui PHP 8.4.26 em `/opt/cpanel/ea-php84/root/usr/bin/php` com as extensões obrigatórias; `php` sem caminho usa 8.3.35. Curl, unzip e Git estão disponíveis; Composer não foi encontrado no PATH. Confirmar PHP dos subdomínios, Composer, document root e acesso seguro ao banco. Não alterar o PHP herdado de outros sites.

## Estado desta entrega

Em produção, `config/session.php` define o cookie de sessão como `Secure` por padrão quando `APP_ENV=production`; `SESSION_SECURE_COOKIE` continua disponível para configuração explícita. Mantenha HTTPS obrigatório e `SESSION_DOMAIN=null` para cookies host-only.

- Estrutura Laravel e Docker local, login, sessão e logout implementados. `/admin/users` permite cadastro, edição com controle de versão, desativação, papéis e reset administrativo de senha. `/admin/account/password` permite trocar a própria senha. As senhas são hasheadas explicitamente nos fluxos de conta; Gates, revogação de sessões e proteção concorrente do último administrador estão implementadas localmente. Ver [USUARIOS-E-PERMISSOES.md](USUARIOS-E-PERMISSOES.md). As migrações **não foram executadas na base real**.
- API pública de leitura preparada para as tabelas legadas de `../scripts/export-posts.sql`: filtra `STATUS_POST = PP`, devolve o contrato esperado pelo site institucional e não expõe não publicados. O HTML dos artigos é sanitizado no servidor por allowlist antes de sair pela API; títulos e demais campos continuam sujeitos a escaping normal. A ordenação usa uma revisão InnoDB com mutex, comparação otimista, chave de idempotência e auditoria, e só é aplicada quando digest, conjunto e posições conferem com os PP atuais. Conflitos retornam `409`; armazenamento indisponível/incompatível retorna `503`. A ordem é auxiliar e não grava nas tabelas antigas MyISAM.
- Nenhum dado da base existente foi alterado. O usuário forneceu `C:\dev\veneza-backups\veneza_pena.sql.gz`: integridade e SHA-256 conferidos, restauração local isolada concluída em MariaDB 10.11.19. O adaptador de leitura retornou os 152 publicados e rejeitou os 32 não públicos. Consulte [RELATORIO-BANCO.md](RELATORIO-BANCO.md) para campos, URLs, inconsistências e limitações de MyISAM; a validação não libera automaticamente escritas em produção.
- O backlog local 02 acrescenta administração de autores e biblioteca de mídias em `/admin/authors` e `/admin/media`. O upload aceita JPEG/PNG/WebP até 2 MB, ajusta o limite de pixels ao orçamento de memória PHP, normaliza derivados e mantém originais/derivados fora de `public/`. O seletor de foto de autor mantém visível o vínculo atual mesmo quando a imagem já não está entre as 300 mais recentes. A biblioteca fornece mídia selecionável para o editor de artigos.
- O backlog local 01 acrescenta listagem, busca, filtros, criação/edição, prévia privada, publicação, ocultação e exclusão lógica de artigos em `/admin/posts`. A criação exige vínculo explícito do usuário com `ID_PESSOA`; novas entradas começam em `PO`. Autor, capa e categorias são selecionados das fontes existentes, sem criar categoria ou pessoa implicitamente. A API pública mantém apenas `PP`. **Escrita fica desativada por padrão** por `PENA_EDITORIAL_WRITES_ENABLED=false` e é recusada pelo serviço se as tabelas envolvidas não estiverem todas em InnoDB. O acervo restaurado continua MyISAM, portanto esta implementação não habilita edição no servidor. Não existe administrador real cadastrado.
- O backlog local 05 acrescenta `/api/admin/v1` e documentação Swagger UI protegida para administradores, com a mesma autenticação e serviços do painel. Não há token permanente ou SSO entre subdomínios. Em produção, as rotas só são registradas com `PENA_API_HOST` configurado. Consulte [API-ADMIN.md](API-ADMIN.md); nada disso foi implantado na ServHost.
- O backlog local 06 acrescenta `GET /api/public/posts/slug/{slug}` por `LINK_POST`, com filtro `PP` e comparação exata, e prepara o site institucional para `/conhecimento/<slug>` renderizado em PHP. Slugs de artigos que já foram publicados não podem ser alterados sem mecanismo de aliases; rascunhos novos continuam editáveis. A camada do site e os testes sintéticos estão em `../site/`; nada foi implantado no domínio principal.
- O backlog 07 tem um [roteiro de homologação, resultados locais e instalação](HOMOLOGACAO-E-RELEASE.md), um empacotador de commit limpo em `../scripts/prepare-pena-release.ps1` e uma matriz MariaDB sintética em `tests/Integration/run-release-matrix.ps1`. A imagem Docker de testes usa PHP 8.4, a linha instalada no CLI da ServHost. Os pacotes só podem ser gerados de commit com árvore limpa; commit e push, por si, não geram pacote nem instalam o sistema. Nenhuma migração, conta ou instalação de produção foi realizada.

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

## Revisão e testes locais

Consulte o [relatório consolidado](RELATORIO-GERAL.md), o [histórico de revisões](RELATORIO-REVISAO-LOCAL.md) e [SEGURANCA-EDITORIAL.md](SEGURANCA-EDITORIAL.md) para resultados, decisões e pendências. PHPUnit usa SQLite sintético para validação funcional, mas concorrência/rollback do ordenamento são testados separadamente contra MariaDB 10.11 em um contêiner descartável, rede desativada e socket dedicado; isso não é simulado por SQLite.

Na raiz do repositório, com dependências já instaladas:

```powershell
docker compose -f PENA/compose.yaml build app
docker run --rm --network none -v "${PWD}/PENA:/app" -w /app pena-app php vendor/bin/phpunit
docker run --rm --network none -v "${PWD}:/app:ro" -w /app node:22-alpine node --test tests/public-posts.test.cjs site/tests/content.test.cjs PENA/tests/js/admin-order.test.cjs PENA/tests/js/admin-media.test.cjs PENA/tests/js/admin-post-media.test.cjs PENA/tests/js/api-docs.test.cjs
```

Os testes de concorrência editorial, autoria/mídia e edição de posts estão em `tests/Integration/` e são executados pelo workflow em MariaDB 10.11 sintético, vazio, sem rede e com socket descartável. O teste de posts verifica rollback e bloqueio em MyISAM; não execute esses scripts contra a restauração privada nem contra produção.

Com o serviço Docker ativo, também é possível executar `docker compose -f PENA/compose.yaml exec -T app php artisan test`. O PHPUnit força o ambiente de teste em `env` e `server`, inclusive quando o Compose fornece variáveis próprias. `tests/TestCase.php` interrompe a suíte antes das fixtures se a configuração não for `testing` + SQLite `:memory:` sem `DB_URL`. Não remova essa trava nem use as fixtures no banco real.

`.github/workflows/pena-tests.yml` prepara as mesmas verificações em push/PR com alterações relevantes e execução manual, sem credenciais e sem deploy. Confira o resultado da execução no GitHub após o push; a existência do arquivo não comprova que o job passou. O job de instalação precisa de rede para obter dependências, mas os contêineres dos testes são executados com `--network none`.

## Portão obrigatório antes de qualquer escrita na base existente

1. Obter da hospedagem um dump completo por canal seguro (por exemplo, exportação pelo painel HTTPS ou dump gerado no servidor e transferido por SFTP/SSH). Solicitar também uma forma segura de acessar o MySQL: TLS habilitado ou túnel SSH. A conexão TCP atual aceita login, mas o servidor informa que **não suporta TLS**; por isso não faremos dump nem migração por essa conexão.
2. Guardar o dump fora do repositório, em `C:\dev\veneza-backups` ou outro diretório privado, verificar o SHA-256 e testar restauração em banco isolado. O backup deve conter estrutura e dados; confirmar também triggers, rotinas e eventos, se existirem.
3. Inspecionar o esquema real e comparar tabelas, campos, índices, estados editoriais e relacionamentos com o adaptador em `app/Repositories/LegacyPostRepository.php`. Confirmar se `pena_admin_users` ou `pena_post_order` já existem antes de aplicar as migrações propostas. Não modificar tabelas legadas às cegas.
4. Só então configurar `.env` com acesso seguro, `APP_DEBUG=false` fora do ambiente local, aplicar migrações revisadas e criar a conta inicial. O comando `php artisan pena:create-admin --backup=<caminho> --sha256=<hash>` solicita nome, e-mail e senha sem mostrá-la no terminal e exige um arquivo de backup não vazio com o hash informado; a restauração deve ter sido verificada manualmente antes. Dentro do Docker, monte o diretório do backup somente para leitura, por exemplo `-v "C:\dev\veneza-backups:/backups:ro"`, e use o caminho `/backups/<arquivo.sql>` no comando. As migrações devem ser revisadas e executadas separadamente após o mesmo portão.

Sem acesso SSH/painel, peça ao provedor um dump por link HTTPS temporário autenticado e habilitação de TLS no MySQL, ou acesso SSH temporário para túnel. Não envie o dump por e-mail sem proteção e não faça a exportação por MySQL sem criptografia.

## Integração do site institucional

Após hospedar o PENA em origem HTTPS, adicionar a origem exata do site em `PUBLIC_SITE_ORIGINS` (lista separada por vírgulas) e configurar no PHP do domínio institucional `VENEZA_PUBLIC_API_ORIGIN=https://api.venezapiscinas.com.br`. `site/assets/js/config.js` ativa automaticamente a mesma API no domínio definitivo HTTPS; o snapshot só é usado na prévia. `GET /api/public/posts` aceita `page` (padrão `1`), `per_page` (padrão `50`, máximo `100`) e `q` (busca de até 150 caracteres) e retorna `{ "data": [...], "meta": { "current_page", "per_page", "total", "last_page", "snapshot" } }`; os itens são resumos sem HTML integral. A busca `q` considera também o corpo, mas devolve somente os metadados dos resultados. O `snapshot` cobre conjunto publicado, ordem efetiva e IDs dos resultados da busca; a API revalida o estado durante a consulta e retorna `409` se publicação ou ordem mudarem no meio da leitura. O site também descarta páginas com snapshots diferentes e solicita recarga após `409`. `GET /api/public/posts/{id}` e `GET /api/public/posts/slug/{slug}` retornam `{ "data": {...} }` com HTML sanitizado, somente para `PP`; ausentes e não publicados retornam 404. A listagem percorre `meta.last_page`. Em modo remoto, erro/404/410 nunca volta ao snapshot local.

## Pendências para concluir o produto

- Backup dos arquivos/mídias, confirmação de objetos eventualmente omitidos pelo dump e atualização do backup perto do deploy. A restauração do dump atual e a leitura do acervo foram verificadas; a API agora detecta deriva concorrente durante a leitura MyISAM, mas coordenação/atomicidade de futuras escritas, autoria órfã e estado `PR` continuam pendentes.
- O fluxo de posts está implementado e testado localmente, mas permanece bloqueado na base real MyISAM. Planejar e autorizar separadamente conversão/coordenação dos escritores, backup atualizado e ensaio de retorno. Cadastro/edição de categorias não foi incluído: o editor apenas vincula categorias existentes. Validar em navegador real antes de ativar.
- Revisar as novas tabelas auxiliares e demais migrações contra o esquema real antes de instalá-las. A ordem editorial não toca o legado, mas as migrações continuam não executadas na base real.
- Testes de integração com cópia restaurada da base real; criação da conta inicial; HTTPS, domínio, CORS e hospedagem PHP.
- Verificação do Swagger UI no subdomínio real `api`, inclusive HTTPS, cookie host-only, cache e CSRF. O smoke test local em Chromium cobre login, `Try it out` GET e logout com SQLite descartável, mas não substitui o ensaio no ServHost.
- Ativar API no site, validar publicação/despublicação, artigos antigos, 404, falha da API e metadados SEO.
