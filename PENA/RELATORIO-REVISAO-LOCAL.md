# Revisão local do PENA — 05/10/2026

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
- Rever payload completo da listagem, paginação e o carregamento do snapshot no site mesmo quando a API estiver configurada.
- Tratar indisponibilidade do banco nas páginas administrativas com a mesma clareza do login/API; revisar cenários de concorrência real e limites de ordenação.

### Segurança editorial e validações não cobertas

- A página legada `../posts.html` ainda usa o snapshot de 184 artigos, incluindo 32 `PO`/`PE`. O site institucional e a API filtram `PP`; isso não remove os registros do arquivo público original. Uma correção futura deve revisar também o artefato publicado, não apenas ocultar cards.
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

Priorizar o backup pelo cPanel e sua restauração isolada. Enquanto ele não chega, implementar os módulos restantes com fixtures explicitamente sintéticas, sem assumir campos ou alterar o esquema real. Instruções de execução dos testes estão no README do PENA.
