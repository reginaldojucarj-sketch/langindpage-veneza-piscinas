# Publicação e verificação da landing

## Escopo e destinos

| Destino | Responsabilidade |
| --- | --- |
| [Equipamentos](https://equipamentos.venezapiscinas.com.br/) | Origem comercial preferida da landing; document root `/home/veneza/public_html/equipamentos.venezapiscinas.com.br`. |
| GitHub Pages | Publicação estática adicional deste mesmo repositório; a URL aparece no ambiente `github-pages` do Actions. |

O destino FTPS é fixo em `/public_html/equipamentos.venezapiscinas.com.br`, relativo à conta que alcança `/home/veneza`. Nunca aponte a landing para `public_html` inteiro. Institucional, PENA/API, loja, imagens legadas e arquivos privados não são destinos deste deploy.

O endereço anterior de orçamento deve conservar redirecionamentos 301 para os mesmos caminhos da nova origem, após validação de DNS, HTTPS e conteúdo. Veja os detalhes e as ressalvas no [README](../README.md#publicação) e na [pesquisa SEO](../PESQUISA-SEO.md).

## O que vai para produção

[scripts/deploy-servhost-landing.py](../scripts/deploy-servhost-landing.py) é a fonte de verdade da seleção. Ele considera somente arquivos públicos versionados:

- `index.html`, `posts.html`, `font-showcase.html`, `robots.txt`, `sitemap.xml` e `.htaccess`.
- Arquivos aprovados em `assets/css/`, `assets/data/`, `assets/images/`, `assets/js/` e `assets/videos/`.

Documentação, scripts, testes, fontes em `assets/sources/`, originais de mídia, arquivos ignorados/não versionados e links simbólicos não entram. O Pages usa a mesma seleção sem `.htaccess`, pois não executa Apache.

Na ServHost, preserve o bloco PHP 8.4 gerenciado pelo cPanel que já integra o `.htaccess`. A landing continua estática: a regra `FilesMatch` impede executar arquivos PHP neste destino. Não use a publicação desta página para mudar configurações de outros domínios.

## Preparar uma atualização

1. Trabalhe em uma branch focada criada de `develop`, conforme [AGENTS.md](../AGENTS.md#fluxo-de-branches-e-commits).
2. Confira diff, mídias, contatos e os dois lados dos contratos HTML/JavaScript. Se atualizar artigos, exporte somente `PP` pelo [fluxo documentado](../README.md#atualização-dos-artigos).
3. Rode os [testes locais](../README.md#testes-locais-sem-publicar) e o checklist visual em desktop, tablet e celular.
4. Integre a branch em `develop`, valide o conjunto e faça o merge de release em `main`.
5. Faça push e acompanhe separadamente os workflows ServHost e Pages. O sucesso de um não comprova o sucesso do outro.

Esta sequência não autoriza exclusões remotas, limpeza de histórico, alteração de banco ou atualização da loja.

## Actions e configuração

O workflow [deploy-servhost.yml](../.github/workflows/deploy-servhost.yml) roda por push em `main` ou acionamento manual na própria `main`. Ele valida artigos, SEO, sintaxe JavaScript, gerador e isolamento do deploy antes de publicar.

Configure neste repositório ou no ambiente `servhost` usado pelo job:

| Nome | Tipo | Uso |
| --- | --- | --- |
| `SERVHOST_FTP_USERNAME` | Secret | Usuário da conta FTP com acesso ao destino exclusivo. |
| `SERVHOST_FTP_PASSWORD` | Secret | Senha dessa conta. Nunca colocar no Git, chat ou logs. |
| `SERVHOST_FTP_HOST` | Variável opcional | Host FTPS; padrão `rv2.servhost.com.br`. A alternativa precisa ter certificado TLS válido. |

Secrets do institucional não são automaticamente compartilhados com a landing. `PENA_REPOSITORY_TOKEN` e credenciais de banco não são necessários neste repositório.

O envio usa uma sessão FTPS explícita na porta 21, modo passivo, certificado validado e canal de dados criptografado. Não faz retries. Cada arquivo vai para um nome temporário, tem o tamanho conferido e só então é promovido por rename. A home é enviada por último. Isso reduz exposição de arquivos incompletos, mas **não é uma transação de todos os arquivos**.

Depois do envio, o script compara SHA-256 em HTTPS da home, artigos, CSS, logo, robots e sitemap. Só após essas verificações o resumo registra publicação confirmada e o commit. Não há exclusões remotas automáticas.

[deploy-pages.yml](../.github/workflows/deploy-pages.yml) também publica apenas `main`, com artefato público novo e filtrado. Não precisa dos secrets FTP. A presença dos dois workflows não significa que ambos conseguiram publicar.

## Confirmar uma publicação

- Confira o commit e o resultado da execução em Actions. `cancelled`, `failure` e uma execução ainda em andamento não são confirmação de atualização.
- Abra a [home](https://equipamentos.venezapiscinas.com.br/) e os [artigos](https://equipamentos.venezapiscinas.com.br/posts.html), conferindo o endereço final e HTTPS.
- Verifique que `/index.html` normaliza para `/`, os canonicals continuam em `equipamentos` e o sitemap lista apenas as duas páginas indexáveis.
- Confira os recursos e interações do checklist. Uma página com HTTP 200 pode continuar servindo arquivos antigos.
- Para uma publicação feita por outro meio autorizado, compare a lista pública versionada com os arquivos instalados e os hashes do commit; não confunda linhas CRLF de um checkout Windows com os bytes canônicos do Git. Preserve o bloco gerenciado do cPanel e registre qualquer diferença explicitamente.
- Registre data, commit, evidência e limitações no marco de versão. Não inclua credenciais nessa evidência.

## Falhas e recuperação

| Sintoma | Próximo diagnóstico seguro |
| --- | --- |
| Secrets ausentes | Conferir os nomes e o escopo no repositório/ambiente `servhost`, sem imprimir valores. |
| `ftps-connect` com timeout | Conferir acesso FTPS e bloqueio de IP com a hospedagem; não repetir envios automaticamente. |
| Erro de certificado TLS | Corrigir host/certificado. Nunca desativar validação TLS para publicar. |
| Erro em `landing-root` | Conferir a raiz da conta e a existência do destino exclusivo. Não mudar para o diretório do institucional. |
| Tamanho ou hash divergente | Não declarar sucesso; identificar arquivos ausentes, cache, destino incorreto ou envio parcial antes de outra publicação. |
| Pages funcionou, ServHost falhou | Tratar como duas publicações independentes; conferir a origem comercial, não apenas a cópia Pages. |

O script informa etapa e categoria fixas do erro, sem respostas brutas do servidor que possam conter segredos. Use o resumo e esses rótulos na investigação.

Não existe rollback automático. Se uma correção exigir voltar o conteúdo, revise o diff do commit/tag desejado numa branch e siga o mesmo Gitflow e validações para um novo release. Não faça force-push, reset destrutivo ou exclusões em outros destinos. Uma tag é referência de código, não backup do servidor nem garantia de compatibilidade futura.

## Estado registrado em 09/10/2026

A landing em produção foi comparada com o commit `32a89fb5af26d76c297785f3dc323a0df2da349d`; consulte [o registro completo](releases/stable-2026-10-09.md). A última execução FTPS consultada estava cancelada. Essa documentação distingue a verificação dos arquivos publicados da disponibilidade da automação; futuras execuções precisam ser conferidas novamente.
