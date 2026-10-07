# API administrativa e documentação protegida — backlog 05

Estado: implementada e testada **localmente**, sem publicação, migração ou escrita na base real. A API pública existente (`GET /api/public/posts` e `GET /api/public/posts/{id}`) continua anônima e retorna somente `PP`.

## Rotas e acesso

Defina `PENA_API_HOST=api.venezapiscinas.com.br` somente quando esse host HTTPS estiver apontando para o `public/` da **mesma aplicação Laravel** usada pelo painel. Em produção, sem essa configuração, as rotas de documentação/API administrativa não são registradas. Com ela, `/` e `/docs` no host da API mostram Swagger UI; o JSON em `/openapi/admin-v1.json` também exige login e papel `admin`. O login fica em `/admin/login` no próprio host da API. O banco de usuários é `pena_admin_users`, mas `SESSION_DOMAIN=null` mantém cookies separados por host: entrar no painel `pena` não autentica automaticamente no host `api`.

A API administrativa está versionada em `/api/admin/v1`, sem duplicar o prefixo `/api`. Todas as operações exigem sessão ativa de administrador, são `no-store, private`, e escritas estão no grupo web do Laravel, com CSRF e limite de 20 requisições/minuto por rota de mutação. Não há CORS com credenciais para ela: Swagger UI e API devem ser usados na mesma origem. Não foram criados tokens para clientes externos. O `requestInterceptor` da UI só aceita a origem atual, envia `Accept: application/json` e o token CSRF em mutações; a validação remota da especificação está desligada. A especificação não lê registros reais e não contém senhas, hashes ou exemplos do backup.

| Recurso | Operações |
| --- | --- |
| Conta atual | `GET me` |
| Usuários | `GET/POST users`, `GET/PATCH users/{id}` |
| Autores | `GET/POST authors`, `GET/PATCH authors/{id}`, `PATCH authors/{id}/activate` e `/deactivate` |
| Mídias | `GET/POST media` (multipart), `GET/PATCH media/{uuid}`, `PATCH media/{uuid}/activate` e `/deactivate` |
| Posts | `GET/POST posts`, `GET/PUT posts/{id}`, `POST posts/{id}/publish`, `/hide` e `/delete` (lógico) |
| Ordem | `GET/PUT posts/order` com revisão, lista completa e chave de idempotência |

Os controllers reutilizam `AdminAccounts`, `AuthorLibrary`, `MediaLibrary`, `PostEditor` e `EditorialOrdering`; não criam outro caminho de gravação. A API de posts segue bloqueada por padrão (`PENA_EDITORIAL_WRITES_ENABLED=false`) e recusa MyISAM mesmo se a flag for ligada. **Não habilitar escrita na base real** sem backup atualizado/restauração, autorização para tratar engines e coordenação do escritor legado. Uploads continuam em armazenamento privado. O Swagger executa requisições reais no ambiente aberto: não usar ações de escrita em produção para “testar a documentação”.

Respostas JSON usam `data`, listas paginadas incluem `meta`; validação retorna `422`, falta de sessão `401`, permissão `403`, ausência `404`, fingerprint/revisão conflitante `409`, CSRF `419`, rate limit `429` e armazenamento indisponível `503` quando tratado pelos serviços. Algumas atualizações de conta/autor/mídia legadas usam `422` para versão obsoleta, como no painel. `GET posts/{id}` devolve HTML sanitizado para prévia privada e um fingerprint que deve acompanhar a atualização ou transição. `POST posts/{id}/delete` exige `confirm_delete=true` e preserva o registro. Senhas nunca são serializadas.

## Validação local

Com dependências Docker instaladas, na raiz do repositório:

```powershell
docker run --rm --network none -v "${PWD}/PENA:/app" -w /app pena-app php vendor/bin/phpunit
docker run --rm --network none -v "${PWD}/PENA:/app:ro" -w /app pena-app php scripts/export-openapi.php
```

O workflow executa o exportador e valida o documento com `@apidevtools/swagger-parser` fixado em `tools/openapi/package-lock.json`, além de PHPUnit. `AdminApiTest` compara **todas** as operações do OpenAPI com as rotas Laravel registradas, verifica proteção da documentação, permissões, CSRF, sessão revogada, contratos CRUD sintéticos, upload, conflito, ordem, falhas internas sem vazamento de SQL e ausência de CORS administrativo. O teste de CORS público existente verifica origem permitida/negada e preflight sem credenciais.

`tests/Browser/browser.mjs` executa um smoke test real em Chromium headless: visitante redirecionado ao login, autenticação com conta **sintética**, abertura do Swagger, consulta autenticada de `GET /api/admin/v1/me` pelo botão “Try it out”, logout e bloqueio do JSON OpenAPI. `tests/Browser/prepare.php` recusa qualquer banco que não seja o SQLite descartável `/tmp/pena-browser-test.sqlite`; `tests/Browser/run-ci.sh` cria rede e volumes Docker temporários, monta o projeto somente para leitura, executa o teste e remove os recursos criados. O workflow inclui esse teste; confira a execução no GitHub após o push. Nunca reutilize a conta sintética ou a chave de teste na hospedagem.

Ainda faltam confirmação de domínio/SSL/PHP web/cookies/cache no ServHost, revisão da base convertida em homologação, conta inicial, deploy e comprovação do workflow no GitHub. O teste local de navegador **não** comprova que os subdomínios reais estão configurados. Não há suporte a tokens para clientes externos. O endpoint público `GET /api/public/posts/slug/{slug}` do backlog 06 consta no OpenAPI protegido.
