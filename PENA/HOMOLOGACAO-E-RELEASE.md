# Backlog 07 — homologação e instalação controlada

Estado em 07/10/2026: **preparação local, sem instalação**. Este documento é um roteiro de decisão e execução assistida; não autoriza trocar arquivos, configurar domínios ou escrever na base de produção. A landing da raiz e a loja ficam fora do corte.

## Portões de liberação

| Portão | Evidência exigida | Situação / próxima verificação |
| --- | --- | --- |
| Fonte | Backlogs 01–06 revisados, commit identificado, árvore limpa, CI aprovado | **Verificar após o push:** commit de release, árvore limpa e resultado do workflow no GitHub |
| Plataforma | PHP web e CLI >= 8.4.1 nos hosts `pena` e `api`; extensões e Composer verificados | **Aberto:** somente CLI 8.4.26 foi observado no Terminal; PHP web não foi medido |
| Arquivos | Inventário e backup restaurável do site atual, `pena`, regras de rewrite e mídias antigas | **Aberto:** backup do banco não substitui arquivos |
| Banco | Dump novo com objetos da origem conferidos, hash, restauração isolada, migrações ensaiadas | **Aberto:** dump privado de 05/10 foi restaurado, mas pode estar desatualizado |
| Escrita editorial | Decisão aprovada sobre MyISAM, escritores legados, conversão/concorrência e retorno | **Bloqueado:** `PostEditor` recusa MyISAM mesmo com a flag ligada |
| Hospedagem | Document roots seguros, HTTPS, cache, CORS, canonical `www`, mídia legada | **Aberto:** raízes vistas no painel não foram verificadas na web |
| Aceite | Homologação visual e funcional assinada; autorização explícita da janela de corte | **Aberto:** nenhum corte autorizado |

Não interpretar “código empacotado”, “testes locais aprovados” e “produção validada” como o mesmo estado. Enquanto o portão MyISAM estiver fechado, **não prometer cadastro/publicação de artigos em produção**. Uma instalação somente de leitura precisaria de aceite explícito como escopo reduzido.

## Evidência local desta rodada

- A imagem `pena-php84-homolog` compilou em **PHP 8.4.26**. `composer check-platform-reqs --no-dev` passou; `composer audit --no-dev --locked` não encontrou avisos nas dependências de produção na consulta executada em 06/10/2026. Auditoria online é pontual, não garantia futura.
- PHPUnit completo em PHP 8.4: **135 testes / 1.078 asserções**, aprovado na validação local de 07/10/2026. JavaScript isolado: **35/35 testes**, aprovado; o teste Chromium do PENA também cobriu envio e seleção de capa entre abas. Site PHP/HTTP e Chromium headless: aprovados com API sintética; testaram URL direta, SEO inicial, sanitização, busca, teclado e larguras de 320/480/1440 px. A listagem também foi exercitada contra origem HTTPS sintética interceptada no navegador, com paginação, busca e falhas sem fallback local; não mede TLS/CORS no servidor real.
- O backup privado previamente verificado foi restaurado **somente em MariaDB descartável sem rede**. As cinco migrações aditivas passaram: 184 artigos (152 `PP`, 26 `PO`, 6 `PE`) preservados; as dez tabelas legadas permaneceram MyISAM; 11 tabelas `pena_*` ficaram InnoDB; 7 autores e 182 atribuições históricas foram copiados. O ensaio não prova que o servidor real continue idêntico ao dump de 05/10.
- A matriz MariaDB sintética isolada passou para acesso (10 disputas), ordem editorial (10 disputas e seis pontos de rollback), autoria/mídia e edição de posts (concorrência, rollback e recusa MyISAM). Uma mensagem `testing.ERROR` no teste de posts corresponde à falha de auditoria **injetada** para verificar rollback; a suíte terminou com `PASS`. A primeira tentativa do executor falhou apenas porque o log apontava para montagem somente leitura; `LOG_CHANNEL=stderr` corrigiu o executor e a matriz completa foi repetida com sucesso. Contêineres e volumes da matriz foram removidos.
- O empacotador foi analisado sintaticamente e seu bloqueio para árvore Git suja foi testado. **Não houve pacote gerado na validação local**, pois a árvore então continha alterações não commitadas. O fluxo completo de ZIP, Composer de produção e instalação no cPanel não foi executado.
- Na validação local de 07/10, nenhum dado remoto foi modificado e não houve migração na origem, criação de conta ou instalação do PENA. Commits, pushes e resultado do workflow devem ser conferidos separadamente no GitHub; não equivalem a deploy na ServHost.

## Inventário e backups antes de qualquer corte

1. No cPanel, registrar para cada host a raiz do documento, PHP web efetivo, HTTPS/certificado, redirecionamentos, cache Nginx e ocupação de disco. O painel mostrou `/home/veneza/public_html` para o principal, subpastas para `pena` e `api`, e `/public_html/loja` para a loja; confirmar os caminhos reais. Não executar remoção recursiva em `public_html`.
2. Fazer backup privado dos arquivos e configurações atuais pelo cPanel, incluindo `.htaccess`, mídia sob o host `pena` (especialmente `/img/source`), uploads e conteúdo da loja. Guardar fora de `public_html`; verificar integridade e ensaiar a extração em destino separado. Registrar local privado, hash, data e responsável — nunca o conteúdo ou credenciais no Git.
3. Gerar novo dump por canal seguro do cPanel/Terminal, incluindo estrutura/dados e confirmando na origem se existem triggers, rotinas e eventos. O dump de 05/10 não prova o estado atual. Conferir hash e restauração em banco isolado; comparar contagens, `LINK_POST`/status, engines, charset, índices e relações. Não utilizar MySQL remoto sem TLS.
4. Trocar credenciais anteriormente expostas por processo privado; não inseri-las em comandos, logs, mensagens, manifesto ou artefato. Preparar `.env` no servidor fora da raiz pública, com permissões restritas. Não usar `migrate:fresh`, `migrate:reset` ou seed de exemplo.
5. Inventariar as URLs antigas de capa/corpo antes de mover o host `pena`. O novo Laravel não substitui automaticamente a árvore de mídia legada. Manter um caminho HTTP seguro e testado para esses arquivos; não copiar a aplicação antiga inteira para `public/`.

## Artefatos reproduzíveis

Após revisão e commit/merge pelo Gitflow, numa árvore **limpa**, `scripts/prepare-pena-release.ps1 -OutputDirectory <diretório-privado-novo>` gera `site.zip`, `pena.zip` e `manifest.json` a partir do commit HEAD. O script não lê o working tree para o conteúdo, recusa diretório de saída existente ou dentro do repositório, instala o `vendor` de produção em PHP 8.4, verifica requisitos de plataforma e exclui testes, documentação de desenvolvimento, `.env` e o snapshot estático dos artigos do pacote de produção. O manifesto registra commit, versão PHP, hash do lock e SHA-256 dos pacotes. Conferir também os arquivos ZIP antes da transferência. O script **não foi executado na validação local de 07/10**; nenhum pacote foi declarado pronto.

Transportar os pacotes pelo gerenciador de arquivos HTTPS do cPanel ou outro canal realmente habilitado. SSH/SFTP externo permanece bloqueado pelo provedor. O Terminal web pode executar verificações, mas não autoriza imaginar um pipeline de deploy remoto. Não usar o workflow `deploy-pages.yml`: ele publica apenas estáticos no GitHub Pages e não instala PHP.

## Homologação isolada

1. Criar cópia de homologação com acesso restrito e `noindex`, banco **próprio**, mídia copiada de modo controlado e sem envio real de e-mail (`MAIL_MAILER=log`). Impedir jobs e integrações que afetem clientes. Não apontar a homologação ao banco de produção.
2. Confirmar PHP 8.4.1+ no **web** e no CLI escolhido (`/opt/cpanel/ea-php84/root/usr/bin/php` foi observado em 8.4.26). Validar extensões exigidas pelo lock, `composer check-platform-reqs --no-dev`, permissões de `storage`/`bootstrap/cache`, limite de upload e armazenamento privado. O container local em PHP 8.4 é equivalente apenas para o código; não mede configuração web da ServHost.
3. Manter código Laravel, `vendor`, `.env`, backups e arquivos privados fora do document root. Expor somente `PENA/public` para `pena` e `api`, se o cPanel permitir ambas as raízes com segurança. Se a raiz não puder ser alterada, **parar** e testar uma disposição alternativa sem copiar o projeto inteiro para `public_html`.
4. Aplicar apenas migrações aditivas revisadas na cópia; confirmar que não alteram as dez tabelas legadas e preservar 184 IDs, 152 `PP`, 26 `PO`, 6 `PE`, slugs e conteúdo. A origem é MyISAM/utf8mb3. Ensaiar separadamente uma estratégia aprovada para escrita; ligar `PENA_EDITORIAL_WRITES_ENABLED` não contorna o bloqueio do serviço.
5. Configurar ambiente de homologação sem valores padrão de produção: `APP_DEBUG=false`, chave exclusiva gerada privadamente, `SESSION_DOMAIN=null`, cookie seguro/HTTPS, `PENA_API_HOST` correspondente, `PUBLIC_SITE_ORIGINS` exatas, `PENA_EDITORIAL_WRITES_ENABLED=false` até liberação formal. No site PHP, definir `VENEZA_PUBLIC_API_ORIGIN` como a origem HTTPS de homologação sem caminho; `/conhecimento/` injeta essa origem pública no HTML para a listagem. Sem a variável, a rota responde 503, pois o pacote não contém o snapshot local. Login no host `api` é separado do host `pena`, embora use a mesma base de usuários.
6. Executar PHPUnit, JS, integração MariaDB e Chromium; repetir no Apache/cPanel os casos de `/admin`, `/docs`, `/openapi/admin-v1.json`, listagem, busca, slug, ID antigo, 404/503, mídia, upload, teclado, 320–480 px e cache. Testar publicação/ocultação **somente** em banco de homologação com engine/concorrência aprovada. Confirmar que `.env`, `vendor`, backups e arquivos privados não respondem por HTTP.

## Corte autorizado — ordem e retorno

Antes da janela, registrar responsáveis, duração esperada, usuários ativos, commit e hashes dos pacotes; obter autorização explícita. Fazer um backup novo imediatamente antes do corte e congelar somente os escritores necessários. Instalar primeiro PENA/API, validar leitura e autenticação, depois o site institucional. Só alterar o domínio principal quando a API pública HTTPS e o SSR estiverem funcionando; **não instalar a landing da raiz**. Preservar a loja e verificar sua URL antes e depois.

Decidir uma única origem canônica: o PHP atual aponta para `https://venezapiscinas.com.br`, mas o cPanel foi visto redirecionando para `www`. Corrigir esse conflito na homologação antes de DNS/rewrite; não criar regras opostas. Para o host `pena`, preservar ou mapear explicitamente as URLs antigas de mídia sob o mesmo host antes de trocar a raiz. Não limpar cache Nginx indiscriminadamente nem permitir cache público de sessão, `/admin`, `/docs`, API autenticada ou artigo removido.

Após o corte, testar de fora: certificado e cadeia HTTPS, home institucional, `/admin` para login PENA, login/logout/revogação, documentação protegida no host `api`, GET público sem rascunhos, slug/ID, OG/canonical no HTML inicial, imagens legadas, upload privado e loja. Somente depois registrar sucesso e encerrar manutenção. Criar a primeira conta com senha escolhida e entregue em canal privado, nunca em arquivo de release ou chat.

Retorno de **código**: manter pacote e configuração anteriores, reverter ponteiros/raízes com registro e testar novamente. Retorno de **dados** não é automaticamente restaurar dump antigo: após qualquer gravação nova, isso perderia posts, contas, uploads ou auditoria. Definir janela de congelamento, reconciliação/exportação das alterações posteriores e responsável pelo aceite; ensaiar esse retorno em homologação. Migrações sem `down` destrutivo exigem correção para frente ou plano específico por tabela. Não executar rollback genérico na produção.

## Registro de execução

Preencher fora do repositório, sem segredos: commit, hashes, PHP web/CLI, versões MariaDB, backup de arquivos e dump com resultado da restauração, document roots, origem canônica, migrações aplicadas, testes por ambiente, URLs verificadas, início/fim da janela, responsáveis, decisão de escrita MyISAM, incidentes e resultado do ensaio de retorno. Se um portão acima não tiver evidência, registrar “não verificado”, não “aprovado”.
