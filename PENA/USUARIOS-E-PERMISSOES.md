# PENA — usuários e permissões (backlog 03)

Implementação local de 05/10/2026. Não instalada na hospedagem; nenhuma alteração na base real ou no dump privado. A landing e o site institucional não foram modificados nesta etapa.

## Matriz inicial (backlog 03)

| Ação | Administrador ativo | Editor ativo | Inativo/anônimo |
| --- | --- | --- | --- |
| Entrar no painel e alterar a própria senha | Sim | Sim | Não |
| Listar, criar, editar, desativar e redefinir senha de usuários | Sim | Não | Não |
| Reordenar posts | Sim | Não | Não |
| Editar conteúdo, autores e mídias | Gate preparado | Gate preparado | Não |
| Publicar e excluir logicamente conteúdo | Gate preparado | Não | Não |

Esta matriz registra a decisão de permissões do backlog 03. Os CRUDs de conteúdo/autores/mídias e a API administrativa foram implementados **posteriormente, apenas localmente**; não estão instalados na hospedagem, e a escrita de posts continua bloqueada na base legada MyISAM. Até confirmação comercial, **publicar, excluir e reordenar são exclusivos de administrador**. Não criar um terceiro papel sem revisão.

As rotas aplicam autenticação, estado/versão da sessão e Gates no backend. Ocultar links no Blade é apenas apresentação. Respostas: anônimo no navegador recebe redirecionamento para login; JSON recebe 401; conta autenticada sem permissão recebe 403; validação JSON recebe 422.

## Contas e migração aditiva

`2026_10_05_000002_add_admin_access_controls.php` adiciona a `pena_admin_users`:

- `role`: `admin` ou `editor`; novos cadastros recebem editor por padrão.
- `is_active`: permite desativação sem apagar a identidade/histórico.
- `auth_version`: invalida autorizações de sessões anteriores.
- `legacy_person_id`: vínculo opcional, único e explícito com `PESSOA_pena.ID_PESSOA`.

Contas preexistentes recebem `admin`, preservando o acesso integral que já possuíam. Não são criadas contas/senhas padrão. A migração exige que `pena_admin_users` já seja InnoDB; não converte tabelas silenciosamente. Cria também `pena_admin_access_lock` e `pena_admin_audit`, ambas InnoDB. Não modifica tabelas legadas MyISAM.

Antes de instalar: backup atualizado/restaurado, revisão do esquema, janela de manutenção sem escritores concorrentes e aplicação das três migrações em ordem. Migrações MySQL envolvem DDL com commits implícitos; uma falha parcial exige inspeção manual, não repetição cega. Rollback automático recusa apagar dados. Todas as sessões anteriores à migração precisarão fazer login novamente.

O comando `pena:create-admin` continua exigindo arquivo físico não vazio e SHA-256; cria administrador ativo através do mesmo serviço, com auditoria `account.created_cli`. Ele não verifica sozinho a restaurabilidade do backup. Nenhuma conta real foi criada nesta entrega.

## Concorrência, sessões e auditoria

Toda escrita de contas feita por `AdminAccounts` trava a mesma linha singleton InnoDB dentro de uma transação. Após obter a trava, relê o ator, sua atividade, papel e versão, e os administradores ativos com leitura bloqueante. Duas desativações/rebaixamentos simultâneos não podem eliminar o último administrador. A garantia vale para esse serviço; scripts que escrevam diretamente no banco contornam as regras e não devem ser usados em produção.

Qualquer alteração efetiva de conta ou senha incrementa `auth_version` e invalida o token de lembrar acesso. Cada requisição protegida relê a conta e compara a versão com a sessão. Desativar e reativar não ressuscita sessões antigas. A invalidação ocorre na próxima requisição; não cancela retroativamente respostas já enviadas. O serviço de contas também revalida o ator dentro da trava para requisições concorrentes. Os arquivos físicos de sessão podem permanecer até a coleta normal, mas não autorizam acesso.

`pena_admin_audit` registra IDs do ator/alvo, ação, nomes dos campos alterados e instante; nunca os valores de senha, hashes ou tokens. Alteração e auditoria fazem parte da mesma transação. Não é um log inviolável contra quem possui acesso direto ao banco. Erros SQL nas escritas de contas são reduzidos a SQLSTATE nos logs, sem bindings. Operações abortadas não geram uma alteração parcialmente auditada.

Essa proteção não resolve concorrência de escrita nos posts MyISAM: isso continua no backlog 04. Não existem tokens de API pessoais emitidos atualmente; uma futura API autenticada deverá verificar estado/versão e reutilizar Gates/serviço, sem assumir que esta sessão autentica automaticamente tokens.

## Senhas e recuperação

- Senha mínima de 12 caracteres, confirmação obrigatória, máximo de 72 **bytes** para evitar truncamento/erro de bcrypt; bytes nulos são rejeitados. Acentos ocupam mais de um byte.
- Os fluxos de cadastro, CLI e alteração de senha aplicam `Hash::make()` explicitamente à senha literal validada antes de atribuí-la ao model. O cast `hashed` do Laravel preserva uma string que já pareça um hash; por isso ele sozinho não é a fronteira de segurança. Uma senha que se pareça com um hash será guardada como o hash dessa string completa, nunca como hash interno aceito sem a política de tamanho.
- Cadastro/edição normalizam e-mail e validam unicidade; campos extras não podem alterar papel, versão, token, ID ou senha fora dos fluxos autorizados.
- `/admin/account/password`: exige a senha atual e encerra as sessões antigas após a troca.
- `/admin/users/{id}/password`: administrador confirma **sua própria senha**, define/confirma a nova senha da conta e entrega por canal privado. Solicitar ao destinatário que a altere. Não há envio por e-mail nem obrigatoriedade técnica de troca no próximo login.
- Reset administrativo e troca pessoal possuem throttle de 5 requisições/minuto, além de CSRF. Nenhuma senha é reapresentada na tela/flash de validação.
- Se o único administrador perder acesso, um operador autorizado usa o bootstrap por Terminal privado, com backup aprovado, cria uma conta administrativa individual e executa o reset auditado pelo painel. Não editar hashes nem usar senha compartilhada.

## Pessoa legada e domínios

O administrador informa `legacy_person_id` somente após confirmar a identidade, na edição da conta. A pessoa precisa existir e não pode estar vinculada a outra conta. Não associar por coincidência de IDs nem copiar credenciais de `PESSOA_pena`. Esse vínculo não equivale a `AUTOR_pena`: autoria editorial é independente.

Contas sem vínculo podem acessar o painel conforme seu papel, mas o futuro CRUD deve recusar operações que exijam `ID_PESSOA` até resolver a identidade. Não inventar pessoa, atribuir todos os posts ao operador ou escolher uma das quatro pessoas existentes arbitrariamente. Nenhuma pessoa legada é criada/editada aqui; a confirmação de identidade é humana.

Decisão inicial para `pena` e `api`: mesmas contas, **logins separados por host**. Manter `SESSION_DOMAIN=null` (cookie host-only), HTTPS, `SESSION_SECURE_COOKIE=true`, HttpOnly e SameSite=Lax em produção; usar nomes de cookie distintos se forem instalações independentes. Não compartilhar cookie em `.venezapiscinas.com.br`, pois loja e outros subdomínios não foram auditados. OpenAPI e login na documentação estão implementados localmente; roteamento e segurança dos domínios ainda exigem homologação.

## Validação e reprodução

Suíte PHPUnit: `tests/Feature/AdminAccessTest.php` amplia matriz de permissões, proteção do último administrador, revogação, reativação, revalidação de ator, senhas, auditoria, falhas SQL sem credenciais, CSRF, throttle, sessão e ausência de cadastro público. `AdminAccountRegressionTest.php` cobre especificamente senhas em formato de hash literal e conflito de versão do formulário; `CreateAdminCommandTest.php` faz a mesma verificação no bootstrap CLI. Fixtures exclusivamente sintéticas em SQLite `:memory:`; a trava em `tests/TestCase.php` foi preservada. Testes existentes de login, bootstrap, API e ordenação permanecem.

O formulário de edição envia `expected_version`, comparada sob trava com `auth_version` do registro. Campo ausente, inválido ou obsoleto retorna erro de validação e não grava/audita nada; o número esperado nunca é atribuído ao model. Reset de senha também incrementa a versão, invalidando formulários de edição abertos anteriormente.

Teste separado `tests/Integration/admin-access-mariadb.php`: exige ambiente `testing`, base vazia `pena_access_test`, socket Unix e marcador explícito. Cria apenas fixtures; testa migração com duas contas preexistentes e dez disputas entre processos PHP independentes (cinco desativações e cinco rebaixamentos). Verifica espera na trava, exatamente uma gravação/auditoria por disputa, sobrevivência do último administrador e preservação de uma tabela legada sintética MyISAM. **Não usa o dump real.**

Na raiz do repositório, com Docker e imagem local `pena-app` já construída, escolha nomes novos para uma execução isolada:

```powershell
$accessTestId = [guid]::NewGuid().ToString('N')
$accessContainer = "pena-access-$accessTestId"
$accessSocket = "pena-access-socket-$accessTestId"
docker run -d --name $accessContainer --network none --tmpfs /var/lib/mysql:rw,size=256m -v "${accessSocket}:/run/mysqld" -e MARIADB_ALLOW_EMPTY_ROOT_PASSWORD=1 -e MARIADB_DATABASE=pena_access_test mariadb:10.11.19 --skip-networking
# Aguarde a inicialização. Este comando deve retornar 1, sem erro, antes de continuar:
docker exec $accessContainer mariadb --protocol=socket -uroot pena_access_test -e "SELECT 1"
docker run --rm --network none -v "${PWD}/PENA:/app:ro" -v "${accessSocket}:/run/mysqld" -w /app -e APP_ENV=testing -e DB_CONNECTION=mysql -e DB_HOST=localhost -e DB_DATABASE=pena_access_test -e DB_USERNAME=root -e DB_PASSWORD= -e DB_URL= -e DB_SOCKET=/run/mysqld/mysqld.sock -e CACHE_STORE=array -e SESSION_DRIVER=array -e PENA_SYNTHETIC_ACCESS_TEST=1 pena-app php tests/Integration/admin-access-mariadb.php
# Remova SOMENTE o contêiner/volume sintéticos criados acima após conferir seus nomes:
docker rm -f $accessContainer
docker volume rm $accessSocket
```

A conta root sem senha existe apenas no contêiner sem rede/portas com dados sintéticos em tmpfs. Não é configuração de produção. O teste recusa uma base já preenchida; cada execução precisa de uma base nova.

O workflow `pena-tests.yml` foi ampliado para repetir o teste sintético MariaDB, além de PHP/JS. Não houve push nem execução no GitHub nesta etapa.

Pendências explícitas: confirmação da política do editor; validação visual/E2E em navegador; execução na versão PHP exata da hospedagem; instalação e criação de contas reais após revisão/backup. O backlog 03 não conclui os demais módulos nem autoriza publicação.
