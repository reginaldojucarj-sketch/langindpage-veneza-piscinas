# Revisão local do PENA — 05/10/2026

Este arquivo preserva a sequência histórica das revisões; números e pendências de seções antigas descrevem o momento em que foram escritos. Para o estado consolidado em 06/10/2026, consulte [RELATORIO-GERAL.md](RELATORIO-GERAL.md).

## Correções após a revisão integral

- A tela administrativa agora recebe a ordem e a revisão do mesmo estado observado. A revisão não é consultada depois de materializar a lista; uma gravação concorrente durante a montagem da página deixa o formulário com a versão antiga correspondente, e o POST retorna `409` sem sobrescrever a ordem recente. Há teste de regressão que intercala uma segunda gravação e verifica a preservação da ordem atual.
- O cookie de sessão é `Secure` por padrão quando `APP_ENV=production`, mantendo `SESSION_SECURE_COOKIE` como sobrescrita explícita. Em hospedagem, manter HTTPS obrigatório e `SESSION_DOMAIN=null`.
- Validação da correção: PHPUnit **87 testes / 577 assertions**; Node **21 testes**; Pint (`--test`) nos cinco arquivos PHP alterados; testes MariaDB sintéticos de concorrência/rollback; leitura do backup restaurado (152 publicados, 32 não públicos rejeitados); `config:show session` com `APP_ENV=production` confirmou `secure=true`. Tudo aprovado; nenhum dado real foi escrito.

## Atualização — backlog 04 (segurança editorial)

Implementação local da ordem concorrente, auditoria editorial, sanitização de saída e validação reutilizável de `utf8mb3`. Nenhuma migração foi aplicada ao banco restaurado/produção; nenhuma tabela legada foi alterada. A decisão e a matriz de falhas estão em [SEGURANCA-EDITORIAL.md](SEGURANCA-EDITORIAL.md).

- Ordenação agora usa mutex e revisão InnoDB, comparação de versão/conjunto publicado, digest, chave idempotente e trilha de sucesso/conflito/falha. O leitor ignora ordem incompleta/obsoleta quando o conjunto `PP` legado muda. Falhas de auxiliares retornam `503`, conflitos `409`, validação `422`; logs não incluem a exceção SQL nem o HTML.
- HTML Purifier 4.19.1 sanitiza na saída com allowlist compatível com as tags usadas pelo leitor do site, URLs HTTP(S) e iframe estrito do YouTube. O acervo não foi regravado. `LegacyUtf8mb3` rejeita caracteres fora do BMP e limites excedidos; falta conectá-lo a um CRUD de posts, que ainda não existe.
- Correções após revisão: conflito HTML reaplica o rascunho aos títulos ainda publicados e inclui novos publicados no fim; API de listagem é paginada, omite HTML integral e verifica o estado editorial antes/depois de cada leitura; o snapshot inclui ordem efetiva e, na busca, os IDs correspondentes ao termo. Mudanças detectadas retornam `409`, sem entregar uma página parcial; a busca continua abrangendo o corpo no servidor, sem devolvê-lo na listagem. Auditoria distingue conflito de revisão, conjunto publicado alterado e reuso indevido de chave. Corrida entre busca digitada e carregamento inicial também está coberta.
- PHPUnit/SQLite: **77 testes / 512 assertions aprovados**. MariaDB 10.11.19 sintético, sem rede/socket temporário: **10 disputas editoriais de duas conexões**, falhas injetadas em seis pontos de rollback, recusa de ordem em MyISAM, retry com erro de deadlock simulado, idempotência e publicação legada durante ordenação aprovados. A checagem separada do último administrador também passou em **10 disputas reais**. Esse retry de deadlock usa erro do driver injetado; as disputas de mutex são conexões MariaDB reais.
- Validação após as correções: **86 testes PHP / 570 assertions**, **21 testes JavaScript**, Pint (`--test`) nos cinco arquivos PHP desta correção, `node --check` nos dois scripts do site e `git diff --check` passaram. A integração em MariaDB 10.11.19 isolado aprovou 10 disputas com duas conexões, seis pontos de rollback, retry de deadlock simulado, idempotência, deriva de publicação MyISAM durante ordenação e durante leitura paginada; auditoria sem dados sensíveis aprovada. O contêiner e o volume temporários foram removidos ao fim do teste.
- Pendências: converter/autorizar mudanças do legado, integrar o guard utf8mb3 às futuras telas de edição, construir CRUD de posts/categorias/autores/mídias, revisar HTML legado com editor, testar no navegador e realizar migração/deploy só após backup e aprovação. Sem commit/push/merge/deploy nesta etapa.

## Atualização — backlog 03

Implementação local de usuários/permissões concluída com política conservadora provisória para editor. Detalhes, matriz, instalação e reprodução: [USUARIOS-E-PERMISSOES.md](USUARIOS-E-PERMISSOES.md). As seções históricas abaixo descrevem a revisão anterior e não substituem esta atualização.

## Correções após revisão de segurança

- O cast `hashed` do Eloquent reconhece uma string que já parece hash e a preserva. Como o serviço aceitava essa string como senha, foi reproduzido um caso em que a senha interna era curta. Cadastro, bootstrap CLI, troca e reset agora chamam `Hash::make()` explicitamente sobre o texto da senha validado. Testes confirmam que a string inteira é a senha; seu conteúdo aparente não é aceito como senha interna.
- Formulários antigos podiam enviar papel/atividade anteriores após outra ação alterar a conta. A tela agora envia `expected_version`, comparada à versão bloqueada no banco dentro da transação. Formulários obsoletos são recusados sem qualquer gravação, e o campo não pode editar `auth_version`. Reset de senha também invalida formulários em aberto ao incrementar a versão.
- Regressões adicionadas em `tests/Feature/AdminAccountRegressionTest.php` e `tests/Feature/CreateAdminCommandTest.php`.
- Validação depois da correção: **68 testes PHP / 455 assertions aprovados**, **16 testes JS aprovados**, Pint (`--test`) aprovado. Teste MariaDB com 10 disputas concorrentes repetido e aprovado; cada disputa aceitou uma alteração, bloqueou a tentativa de retirar o último administrador ativo e preservou a auditoria. `git diff --check` aprovado.

- Cadastro, edição, ativação/desativação, papéis, troca/reset de senha, vínculo explícito com pessoa legada e auditoria administrativa implementados. Último administrador protegido por trava/transação InnoDB; atores e sessões são revalidados.
- **61 testes PHP / 380 assertions**, aprovados pelo serviço Docker (`php artisan test --compact`). Suíte isolada via `composer:2` também executada. **16 testes JS aprovados**. Laravel Pint aplicado; `git diff --check` validado.
- Teste separado em MariaDB **10.11.19**: migração aditiva com contas fictícias e **10 disputas concorrentes** (desativação/rebaixamento), todas aprovadas. Uma alteração por disputa, último administrador e auditoria preservados. Tabela MyISAM sintética preservada. Isso não valida escrita nos posts MyISAM.
- Sem novas dependências de aplicação, alteração da landing/site, migração na base real ou uso de credenciais de produção. Nenhuma conta real criada. Esta etapa não fez commit/push/merge/deploy; CI foi ampliado localmente, não acionado.
- Pendências: confirmar se editor pode publicar/excluir/reordenar (por enquanto não); testes visuais/E2E em navegador; PHP exato da hospedagem e instalação após backup atualizado. CRUD editorial, mídia, OpenAPI e domínios continuam nos demais backlogs.

Arquivos desta etapa (além desta documentação):

- Backend: `app/Models/AdminUser.php`, `app/Services/AdminAccounts.php`, `app/Http/Middleware/EnsureActiveAdminSession.php`, `app/Http/Controllers/Admin/LoginController.php`, `app/Http/Controllers/Admin/UserController.php`, `app/Providers/AppServiceProvider.php`, `app/Console/Commands/CreateAdminUser.php`, `routes/web.php`.
- Banco: `database/migrations/2026_10_05_000002_add_admin_access_controls.php`.
- Interface: `resources/views/admin/dashboard.blade.php`, `users.blade.php`, `user-edit.blade.php`, `password.blade.php`, `password-fields.blade.php` (todos na mesma pasta); `public/admin-users.css`.
- Testes: `tests/Feature/AdminAccessTest.php`, `AdditiveMigrationsTest.php`, `AdminAndPublicPostsTest.php`, `CreateAdminCommandTest.php` (na mesma pasta Feature); `tests/Support/LegacyDatabaseTestCase.php`; `tests/Integration/admin-access-mariadb.php`; `../.github/workflows/pena-tests.yml`.
- Contexto: `README.md`, `AGENTS.md`, `USUARIOS-E-PERMISSOES.md` e status do prompt local ignorado `.tmp-backlog-pena/backlog-pena-03-usuarios-e-permissoes.md`.

Alterações prévias de vistoria do banco/contexto foram preservadas, não refeitas nesta etapa.

**Atualização posterior:** o backup real foi recebido, restaurado e inspecionado; ver [RELATORIO-BANCO.md](RELATORIO-BANCO.md). O adaptador de leitura passou na restauração MariaDB. As referências abaixo à falta do backup descrevem a revisão inicial, anterior à entrega do arquivo; as pendências de CRUD, concorrência, arquivos/mídias e implantação permanecem.

## Conclusão

O núcleo já implementado passou nos testes locais ampliados, mas **o PENA ainda não está pronto para produção**. Login, cadastro de administradores, ordenação e API pública estão implementados; o gerenciamento completo do acervo não está. Nenhuma credencial de produção foi utilizada, nenhum dado remoto foi modificado e nenhuma migração foi aplicada na base real nesta revisão.

Trabalho mantido localmente na branch `feature/pena-admin-api`, sem commit, push, merge ou deploy nesta etapa. O workflow de CI foi preparado, não acionado no GitHub.

## Resultados executados

- PHP: **39 testes e 214 assertions passaram**, tanto pelo serviço Docker (`php artisan test --compact`) quanto em contêiner separado com rede desabilitada (`php vendor/bin/phpunit`). Execução separada: PHP 8.5.11 / PHPUnit 12.5.38.
- JavaScript: **16 testes passaram**, em Node 22 via Docker, também com rede desabilitada.
- `composer validate --no-check-publish`: válido.
- `node --check`: aprovado nos dois scripts de aplicação modificados (`content.js` e `admin-order.js`).
- Laravel Pint aplicado nos arquivos PHP alterados e nos testes; `git diff --check` sem erros de espaços.
- Não foi medida cobertura percentual de código. Quantidade de testes não equivale a ausência de bugs.

### Organização das suítes

| Arquivo | Escopo | Testes |
| --- | --- | ---: |
| `tests/Unit/LegacyPostDataTest.php` | Contrato de dados, tipos, campos opcionais, acentos e precedência de campos legados, sem banco | 4 |
| `tests/Feature/AdminAndPublicPostsTest.php` | Fluxos existentes de login/logout, cadastro, ordem, API e CORS | 8 |
| `tests/Feature/AdminSecurityTest.php` | Mutações sem login, CSRF efetivamente ativo, throttle, validação, hash e escape de nomes | 7 |
| `tests/Feature/PostOrderValidationTest.php` | Payload inválido, lista desatualizada, tabela ausente, reversão e preservação dos posts | 4 |
| `tests/Feature/PublicApiTest.php` | Estados não publicados, relações, ordem, erros 503, contrato, CORS e métodos somente leitura | 8 |
| `tests/Feature/CreateAdminCommandTest.php` | Hash/arquivo vazio, tabela ausente, cadastro sintético, confirmação e duplicidade | 5 |
| `tests/Feature/AdditiveMigrationsTest.php` | Migrações reais em esquema sintético e recusa de rollback destrutivo | 3 |
| `tests/js/admin-order.test.cjs` | Ordem e foco com DOM simulado | 4 |
| `../site/tests/content.test.cjs` | API/snapshot, publicação, falhas, URLs, busca, ordem e integridade do snapshot institucional | 12 |

As fixtures foram centralizadas em `tests/Support/LegacyDatabaseTestCase.php`. O teste genérico `true === true` foi substituído por testes de comportamento real. O normalizador foi extraído para `app/Support/LegacyPostData.php` sem mudar o contrato da API.

## Problemas corrigidos

1. **Isolamento do banco nos testes.** O Compose exportava variáveis em `$_SERVER` que prevaleciam sobre a configuração `env` do PHPUnit. A execução inicial tentou usar o MySQL não configurado e falhou antes de criar tabelas. Agora `env` e `server` forçam SQLite em memória; uma trava interrompe a suíte se a configuração efetiva não for segura. A rodada final foi também executada sem rede.
2. **Cadastro retornava HTTP 500 para e-mail em formato de array.** Normalização agora só ocorre para strings; a entrada inválida retorna 422, sem criar conta.
3. **Ordenação retornava HTTP 500 para array associativo.** A validação agora exige lista sequencial. Testes comprovam que listas inválidas não modificam a ordem anterior.
4. **Foco na ordenação.** Ao mover um item para uma extremidade, o foco não tenta mais voltar ao botão que acabou de ser desabilitado; usa o outro controle habilitado do mesmo item.
5. **Detalhe de artigo com ID divergente.** O site rejeita uma resposta da API cujo ID não corresponda ao artigo solicitado.
6. **Documentação de implantação.** Registrada a exigência efetiva de PHP >= 8.4.1 do lock e corrigida a descrição dos arquivos publicados pelo Pages no README do site.

## O que ainda falta

### Antes de qualquer escrita na base existente

- Obter dump completo por HTTPS/cPanel, guardar fora do repositório, calcular SHA-256 e validar restauração em MySQL/MariaDB isolado. O arquivo de teste do comando é fictício e **não é um backup real**.
- Confirmar esquema, índices, relacionamentos e semântica de `PP`/`PO`/`PE`. As consultas ainda não foram testadas contra uma restauração real.
- Conferir existência prévia das tabelas auxiliares e revisar migrações. O comando de cadastro verifica arquivo/hash, mas não comprova sozinho a restaurabilidade nem impede outras escritas manuais.

### Desenvolvimento do produto (não depende de SSH para avançar)

- CRUD de posts, autores e mídias; edição/desativação de usuários.
- Papéis/permissões e auditoria: todos os usuários cadastrados atualmente são administradores completos.
- Upload seguro, limites de mídia e validação/sanitização de conteúdo no servidor.
- Documentação OpenAPI e interface de consulta protegida por autenticação.
- A listagem da API já foi reduzida e paginada neste seguimento; falta validar a integração real em HTTPS quando a hospedagem estiver pronta.
- Tratar indisponibilidade do banco nas páginas administrativas com a mesma clareza do login/API; revisar cenários de concorrência real e limites de ordenação.

### Segurança editorial e validações não cobertas

- Na data desta revisão, a página legada `../posts.html` ainda usava o snapshot de 184 artigos, incluindo 32 `PO`/`PE`. O arquivo local foi posteriormente substituído por um snapshot com somente 152 `PP`, e o gerador/leitor receberam filtros explícitos. Isso não apaga versões já publicadas nem o histórico Git; verificar o artefato remoto antes de afirmar a remoção em produção.
- Os testes JavaScript usam stubs de DOM; **não são testes completos de navegador nem comprovam a sanitização do HTML em um DOM real**. Faltam testes E2E de login, teclado, leitor, sanitização/XSS, responsividade e upload quando implementado.
- Os testes de migrações em SQLite não provam compatibilidade MySQL, travas concorrentes ou restauração do backup real.
- Rever cache do Nginx, sessão e respostas públicas para não servir conteúdo removido ou páginas administrativas indevidamente.
- A suíte foi executada em PHP 8.5.11; validar também a versão exata disponibilizada pela hospedagem antes do deploy.

### Hospedagem

- Chamado ServHost **#091143**: SSH externo bloqueado em hospedagem compartilhada; suporte ofereceu Terminal pelo cPanel. Usuário pediu liberação temporária, ainda não confirmada na captura fornecida. Não assumir SFTP externo nem túnel MySQL.
- Confirmar PHP web e CLI >= 8.4.1, extensões, Composer, HTTPS dos subdomínios, document root `public`, permissões e limites de upload.
- Definir hostname da landing e configuração final de `pena`/`api`, inclusive a autenticação da documentação no subdomínio da API.
- Criar administrador real somente após backup/restauração e revisão das migrações. As contas dos testes desaparecem ao terminar a execução.
- Ativar `apiBaseUrl` apenas depois de API HTTPS funcional, CORS revisado e validação de publicação/despublicação.

## Próxima etapa sugerida

Priorizar o backup pelo cPanel e sua restauração isolada. Enquanto isso, implementar os módulos restantes com fixtures explicitamente sintéticas, sem assumir campos ou alterar o esquema real. Instruções de execução dos testes estão no README do PENA.

## Atualização — backlog 02 (06/10/2026)

Autores e biblioteca de mídias foram implementados localmente, preservando todo o histórico e as alterações preexistentes da branch. A migração `2026_10_05_000004_create_author_media_library.php` cria somente tabelas InnoDB aditivas para autores, mídia, vínculos/snapshots e auditoria; a origem MyISAM e os arquivos legados não são alterados. Ela **não foi aplicada** à restauração privada nem ao servidor.

O painel tem `/admin/authors` e `/admin/media`. Autores podem ser pesquisados, cadastrados com vínculo explícito a pessoa, editados com controle de versão e desativados/reativados sem substituir autoria histórica. Upload aceita JPEG, PNG e WebP de até 2 MiB e 6.000 px por lado. O teto absoluto de 20 MP é reduzido conforme o limite de memória PHP e o uso corrente antes de decodificar; o cálculo reserva espaço para orientação EXIF, derivado e saída codificada. A aplicação verifica MIME/conteúdo/extensão/dimensões, decodifica e regrava a imagem para remover metadados, corrige orientação EXIF de JPEG e gera derivado de no máximo 1.800 px. O texto alternativo pode ser editado depois do upload; versão e auditoria evitam sobrescritas silenciosas. Nomes de armazenamento são UUIDs; originais e derivados ficam fora de `public/`, em discos privados. Falha no storage/metadados aciona compensação de arquivos e resposta genérica rastreável.

A biblioteca inventaria cadastros de imagens/capas e referências `<img>` do conteúdo legado usando parser local, sem baixar URLs. Prévia de mídia antiga exige host HTTPS permitido em `PENA_LEGACY_MEDIA_HOSTS`; a URL original permanece intacta. A mídia nova é servida apenas por rota com UUID, `nosniff`, CSP e resposta inline. Não há exclusão física. A desativação recusa mídia referenciada por autor, vínculo editorial, capa ou corpo legado e preserva o arquivo.

**Limite intencional:** o CRUD de artigos do backlog 01 ainda não existe. O seletor de mídia emite o evento `pena:media-selected` e oferece URL/texto alternativo para integração, mas esta etapa não grava a associação de artigo; o autor/mídia selecionados serão consumidos quando o editor for implementado. Nenhuma capa antiga foi regravada. A configuração padrão de hosts não substitui a conferência das origens reais das imagens antes de usar a biblioteca.

Validação desta rodada:

- PHPUnit: 102 testes e 759 assertions passaram (`docker compose -f PENA/compose.yaml run --rm --no-deps app php artisan test --compact`).
- JavaScript: 24 testes passaram (site institucional, ordem editorial e seletor de mídia); `node --check PENA/public/admin-media.js` passou.
- MariaDB 10.11.19 sintético, isolado, em tmpfs e sem rede: migração aditiva e snapshot de assinatura histórica; autoria órfã zero não inferida; duas edições concorrentes do autor resultaram em um salvamento e um conflito; inserções concorrentes do mesmo checksum resultaram em uma linha; tabelas legadas permaneceram MyISAM e novas tabelas InnoDB.
- Pint `--test`: 57 arquivos PHP aprovados. `composer validate --no-check-publish` e `git diff --check` passaram.

Os testes não incluem navegador real/visual, arquivos presentes no servidor, limites efetivos do PHP web/Apache/Nginx, continuidade de URL antiga na hospedagem ou implantação. Migração e publicação permanecem bloqueadas até backup atualizado, ensaio de restauração, revisão específica, configuração e autorização.

Revisão posterior dos achados: o formulário de autores agora inclui sempre a foto vinculada, mesmo fora das 300 imagens recentes ou inativa. Salvar outro campo mantém o vínculo; apenas uma escolha explícita por remover a foto o limpa. Novos vínculos continuam exigindo imagem ativa. O orçamento de GD, com reserva de 64 MiB, limita imagens grandes antes da decodificação no PHP web. A fórmula tem teste unitário e a preservação da foto tem teste HTTP com mais de 300 mídias sintéticas. Confirmar o `memory_limit` real do subdomínio e testar upload grande com EXIF na homologação.

Resultado após essas correções: 104 testes PHP e 774 assertions aprovados; Pint `--test` aprovou 58 arquivos PHP. `phpunit.xml` usa 512 MiB apenas durante a suíte longa, que compartilha um processo e acumula memória entre testes. O limite de cada requisição web continua vindo da configuração PHP do servidor. O workflow passou a executar PHPUnit na imagem PHP do PENA, que inclui GD/EXIF; `composer:2` não tinha GD e falharia nos testes de upload.

Validação adicional local: `ImageNormalizerTest` agora gera JPEGs sintéticos com orientação EXIF 6 e executa o processamento em subprocesso com `memory_limit=128M`, independente dos 512 MiB da suíte. Uma imagem de 2.400 × 1.800 é aceita e orientada; outra de 4.000 × 3.000 é recusada antes da decodificação pelo orçamento dinâmico, embora fique abaixo do teto absoluto de 20 MP. As chamadas a `imagedestroy()`, obsoletas no PHP 8.5 usado no Docker, foram removidas. A suíte final, em contêiner sem rede, aprovou **105 testes e 790 assertions**; Pint `--test` passou nos quatro arquivos PHP tocados. O teste não substitui a aferição do limite de memória, extensões e uploads do PHP web da ServHost, nem validação visual em navegador ou execução do workflow no GitHub.

## Atualização — backlog 01 (posts, 06/10/2026)

`/admin/posts` agora oferece listagem paginada, busca/filtro por estado, criação como `PO`, edição, prévia privada sanitizada e ações separadas de publicar, ocultar e excluir logicamente. `PostContentRequest` valida tipos e limites; `PostEditor` aplica sanitização, restrição `utf8mb3`, slug exclusivo, vínculo explícito com `ID_PESSOA`, autor/mídia ativos na nova atribuição, categoria principal e múltiplas categorias, fingerprint de concorrência e auditoria. Escrita de post, vínculos, estado editorial e auditoria é transacional **somente quando todas as tabelas envolvidas são InnoDB**. Publicação/ocultação invalida a ordem manual; a API pública continua filtrando apenas `PP` e responde com `Cache-Control: no-store, private`.

`PENA_EDITORIAL_WRITES_ENABLED=false` é o padrão; mesmo com a flag ligada, a vistoria de engines recusa MyISAM. A base real permanece MyISAM e nenhum post foi gravado nela. O teste MariaDB 10.11 sintético, em tmpfs e sem rede, cobriu criação/publicação/ocultação em InnoDB, duas edições concorrentes com um sucesso e um conflito, reversão após falha de auditoria e recusa de escrita ao mudar a tabela sintética para MyISAM. O contêiner e volume temporários foram removidos. A suíte PHP final sem rede aprovou **115 testes / 867 assertions**; Pint `--test` passou nos 9 arquivos PHP novos/alterados. O workflow local ganhou esta integração, mas ainda não foi executado no GitHub.

Permanecem pendentes: ensaio com cópia revisada do esquema real já convertido/sem escritor legado, autorização de conversão e plano de retorno, migrações/conta na hospedagem, verificação visual e acessível em navegador, cadastro/edição de categorias e decisões sobre `PR` e autores órfãos. Não houve commit, push, migração real ou deploy nesta etapa.

## Atualização — backlog 05 (API e Swagger, 06/10/2026)

API administrativa implementada localmente em `/api/admin/v1` para usuários, autores, mídias, posts e ordem, usando somente os serviços já existentes e sessão/CSRF do painel. Administrador pode abrir `/docs` e `/openapi/admin-v1.json`; visitante/editor não. Em produção, o host explícito `PENA_API_HOST` é obrigatório para registrar essas rotas. Swagger UI 5.33.1 está hospedado localmente, com licença e sem envio da especificação ao validador externo. A API pública atual permanece anônima e restrita a `PP`.

Validação final local: **125 testes PHP / 977 assertions** aprovados em Docker sem rede. Os testes cobrem bloqueio por papel/sessão, revogação, CRUD sintético, upload privado, estados editoriais, CSRF, conflito de ordem, falhas internas sem SQL exposto, ausência de CORS administrativo e paridade entre 28 operações documentadas/rotas registradas. `@apidevtools/swagger-parser` 13.1.0 validou formalmente o OpenAPI exportado (**28 operações**); oito testes JavaScript passaram e Pint `--test` passou nos cinco arquivos PHP tocados nesta continuação. Em Chromium headless, com SQLite descartável e diretórios graváveis em volumes temporários, passaram login no host da API, abertura do Swagger, “Try it out” de `GET /api/admin/v1/me`, logout e bloqueio posterior do JSON. O workflow agora inclui esse smoke test, ainda não executado no GitHub. Não houve escrita na base real, migração, commit, push ou deploy. Faltam validação da hospedagem/domínio real/cookies/HTTPS e ensaio em homologação; não considerar a documentação operacional no servidor ainda.

## Atualização — backlog 06 (site e URLs amigáveis, 06/10/2026)

`GET /api/public/posts/slug/{slug}` consulta o `LINK_POST` existente com `PP` estrito, comparação binária em aplicação e nova verificação de estado ao carregar o detalhe. O endpoint por ID permanece. A documentação OpenAPI passou a ter **29 operações** válidas. Para não quebrar URLs sem tabela de aliases, `PostEditor` aceita mudar slug somente em rascunho nunca publicado; artigo já publicado ou depois oculto mantém o slug. Nenhuma migração nem escrita real foi feita.

O site institucional ganhou renderização PHP sem framework em `site/article.php` e `site/lib/knowledge.php`: `/conhecimento/<slug>` consulta a API pública, devolve 404/410/503 reais, escapa metadados, sanitiza o corpo novamente e gera título, descrição, canonical, Open Graph e JSON-LD no HTML inicial. `/artigo.html?id=N` redireciona ao slug somente para artigo publicado; `/admin` usa destino fixo. A listagem mantém busca/filtros/ordem/destaque sem duplicação, passou a carregar o snapshot local apenas sob demanda fora do domínio definitivo, e usa API sem fallback no domínio definitivo. A prévia GitHub Pages permanece estática; PHP e `.htaccess` não funcionam lá.

Validação local: **127 testes PHP / 999 assertions** na suíte PENA; 20 testes JavaScript do site; teste PHP/HTTP com API sintética para URL direta/recarregamento, estados, ID antigo, `/admin`, SEO inicial e sanitização; Chromium headless para busca, destaque, teclado, 320/480/1440 px, imagens/embeds, fallback visual e ausência de overflow. Pint `--test` aprovou os sete arquivos PHP do PENA alterados; OpenAPI validado formalmente com 29 operações. O workflow foi ampliado, mas **não foi executado no GitHub**. Ainda faltam ensaio Apache/cPanel e API reais, CORS/HTTPS, mídia, cache Nginx e decisão sobre o redirecionamento atual para `www` antes de usar canonical sem `www`. Sem commit, push, merge ou deploy nesta etapa.

## Atualização — backlog 07 (preparação de homologação, 06/10/2026)

O roteiro de portões, backups, artefatos, homologação, corte e retorno está em [HOMOLOGACAO-E-RELEASE.md](HOMOLOGACAO-E-RELEASE.md). A imagem local foi alinhada a PHP 8.4; `composer check-platform-reqs --no-dev`, auditoria pontual de dependências, PHPUnit (127/999), JavaScript (29/29), site PHP/HTTP e Chromium passaram. As cinco migrações aditivas foram ensaiadas em restauração privada isolada do dump de 05/10, preservando os 184 posts e as dez tabelas MyISAM; 11 novas tabelas ficaram InnoDB. A matriz concorrente MariaDB sintética passou após corrigir o log do executor para stderr. O empacotador recusa árvore suja e não produziu pacote. A homologação na ServHost, backup atualizado de arquivos/banco, decisão MyISAM, conta inicial, CI remoto e autorização de corte permanecem pendentes. Nenhum dado de produção foi escrito ou publicado.
