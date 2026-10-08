# Site institucional da Veneza Piscinas — versão provisória

Esta pasta é um protótipo autônomo para o futuro repositório do site institucional. A landing page existente na raiz do repositório não foi alterada. A prévia estática usa HTML, CSS e JavaScript puro, sem build. A publicação definitiva dos artigos acrescenta uma pequena camada PHP sem framework para devolver HTML e metadados já renderizados.

No repositório atual, o workflow de GitHub Pages publica os HTMLs da raiz, as subpastas públicas `assets/{css,data,images,js,videos}`, os HTMLs de `site/` e `site/assets/{css,data,images,js}` em cada push para `main`. `PENA/` e `assets/sources/` ficam fora dos novos artefatos. O site institucional tem um workflow específico para publicação direta na ServHost, cuja ativação depende dos secrets descritos abaixo; a landing da raiz continua separada.

## Publicação automática na ServHost

O workflow [deploy-servhost.yml](../.github/workflows/deploy-servhost.yml) publica o conteúdo institucional de `site/` em cada push para `main`, usando FTPS explícito em `rv2.servhost.com.br` com destino `/public_html`. Um commit local só chega ao servidor depois do push e da execução bem-sucedida do workflow.

Configure em **Settings → Secrets and variables → Actions** do repositório os secrets `SERVHOST_FTP_USERNAME` e `SERVHOST_FTP_PASSWORD`, com as credenciais de uma conta FTP que alcance `/home/veneza/public_html`. Não salve essas credenciais em arquivos versionados. Depois do push, acompanhe o workflow em **Actions**; para repetir uma execução, use **Re-run jobs** ou **Run workflow** selecionando `main`.

Antes do envio, o workflow verifica os snapshots públicos, o JavaScript, as rotas PHP com API sintética e o layout mobile/tablet em Chromium isolado. O envio exige TLS válido, não faz tentativas automáticas repetidas e só é confirmado após comparar o SHA-256 da home, do CSS e da logo servidos por HTTPS.

O pacote contém os HTMLs institucionais, as rotas PHP, `lib/`, os recursos públicos e `.htaccess`. O snapshot `assets/data/posts-data.js`, testes, documentação, `PENA/`, fontes de mídia e a landing da raiz não entram nessa publicação. O envio atualiza apenas os arquivos presentes no pacote e não faz limpeza ampla do servidor: `loja`, `api.pena`, `pena.venezapiscinas` e as mídias legadas são preservados.

O `.htaccess` desativa a listagem de diretórios, prioriza `index.html`, define a origem da API pública e restringe os redirecionamentos e rotas ao domínio institucional, com ou sem `www`. O painel e a API estão no [repositório PENA](https://github.com/fesizw/PENA); sua instalação permanece separada e pendente. Este workflow não instala dependências, configura o banco nem ativa o sistema Laravel. A Central de Conhecimento depende dessa API para funcionar em produção.

## Páginas

| Arquivo | Conteúdo |
| --- | --- |
| `index.html` | Home, necessidades do visitante, projeto hidráulico e entrada para as demais páginas. |
| `solucoes.html` | Soluções organizadas pela necessidade da piscina. |
| `produtos.html` | Vitrine consultiva das linhas atualmente apresentadas na landing. |
| `conhecimento.html` | Prévia estática da Central de Conhecimento. Na hospedagem PHP, `/conhecimento/` serve a mesma página. |
| `artigo.html?id=<id>` | Leitor legado para a prévia estática; na hospedagem PHP, redireciona por ID para a URL canônica. |
| `/conhecimento/<slug>` | Página individual renderizada pelo PHP (`article.php`) com 404/410/503 reais e SEO no HTML inicial. |
| `projetos.html` | Seleção de projetos e imagens já publicados na landing. |
| `veneza.html` | Apresentação institucional, diferenciais, clientes e parceiros. |
| `contato.html` | Dados de contato e formulário que prepara uma mensagem para o WhatsApp. |

As imagens institucionais do protótipo estão em `assets/images/`. As capas e imagens dos artigos históricos ainda usam URLs do PENA; o site também usa Google Fonts, com fontes de reserva locais. Nenhuma página usa caminhos de arquivo relativos à landing fora desta pasta.

## Executar

Abra `site/index.html` no navegador ou, na raiz deste repositório, sirva a pasta com um servidor HTTP:

```powershell
cd site
py -m http.server 8000
```

Se o Python não estiver disponível, qualquer servidor estático serve **para a prévia**. URLs amigáveis dependem de PHP, cURL, DOM, mbstring e `mod_rewrite` Apache; o GitHub Pages não executa essa camada. Ao levar para o novo repositório, copie **o conteúdo de `site/`** para a raiz do novo projeto, mantendo os caminhos relativos. Antes de misturar `.htaccess` com a hospedagem existente, revisar as regras atuais e preservar a loja e os arquivos legados.

## Fontes de conteúdo

- A landing page atual (`../index.html`) e o `../README.md` forneceram as informações institucionais, portfólio, produtos e contatos já usados pela Veneza.
- O PDF manuscrito enviado pelo diretor orientou a estrutura: Home, Soluções, Produtos, Aprenda, Projetos, Veneza e Contato, com Soluções organizadas pela necessidade do cliente.
- Os dois textos anexados foram tratados como propostas estratégicas, não como comprovação de produtos, clientes ou serviços. O protótipo aproveita o conceito de atendimento consultivo e mantém as ofertas já presentes na landing.

## Antes de publicar

- Confirmar com a direção a redação institucional, os 35 anos de experiência, os nomes de clientes e a permissão de uso das imagens.
- Revisar modelos e disponibilidade de produtos. As fotos são exemplos, não uma promessa de estoque.
- Confirmar telefone, e-mail, endereço e horário de atendimento. O horário não foi incluído por não estar documentado.
- Definir domínio, URL canônica, política de privacidade, analytics e requisitos legais quando houver publicação ou coleta de dados.
- Revisar editorialmente o acervo histórico antes da publicação definitiva; textos antigos podem mencionar preços, normas ou produtos desatualizados.
- Se forem adicionadas calculadoras ou recomendações automáticas, validar fórmulas e limites com um profissional responsável. O formulário atual apenas organiza uma mensagem para atendimento humano.
- Ao separar esta pasta em outro repositório, adaptar o workflow de publicação e configurar os secrets de FTPS nesse novo destino.

## Arquivos principais

`assets/css/site.css` centraliza o visual e a responsividade. `assets/js/site.js` controla o menu no celular e o formulário de contato. Os dados do WhatsApp são montados no navegador; o site não armazena as respostas do formulário.

## Central de Conhecimento e futura API do PENA

O nome da seção é **Central de Conhecimento** porque o acervo vai além de piscinas: inclui tratamento da água, equipamentos, aquecimento, banheiras, saunas e segurança. A página `conhecimento.html` preserva as seis orientações curtas anteriores e acrescenta um artigo em destaque, busca, filtro por assunto e cartões que levam a `artigo.html?id=<id>`. O destaque é o primeiro item da ordem recebida, sem repetição na lista. A cópia local em `assets/data/posts-data.js` contém 152 registros `PP` da extração de 17/09/2026. O snapshot atual da landing também foi limitado a `PP`; os 32 registros não públicos permanecem apenas na base privada e possivelmente no histórico Git anterior. Nenhuma base de dados foi alterada.

`assets/js/config.js` habilita automaticamente `https://api.venezapiscinas.com.br` e links `/conhecimento/<slug>` apenas quando o site estiver em HTTPS nos hosts `venezapiscinas.com.br` ou `www.venezapiscinas.com.br`. Em localhost/GitHub Pages, a prévia usa o snapshot local, carregado **sob demanda**; no domínio definitivo o arquivo de quase 1 MB não é carregado nem usado como fallback. Se a API falhar ou retirar um artigo, o site mostra indisponibilidade/404, nunca a cópia antiga. Não implante o site no domínio definitivo antes de configurar a API HTTPS e `PUBLIC_SITE_ORIGINS` no PENA.

Na homologação HTTPS com outro hostname, `conhecimento/index.php` injeta no HTML a origem pública de `VENEZA_PUBLIC_API_ORIGIN` (apenas HTTPS, sem credenciais), que `config.js` usa para a listagem. Sem essa variável, a rota PHP responde 503 em vez de tentar carregar o snapshot que não entra no pacote de produção. A prévia estática local continua autônoma. O SSR de artigos usa a mesma variável no servidor; validar o CORS da origem de homologação separadamente.

`assets/js/content.js` consulta `GET {apiBaseUrl}/api/public/posts?per_page=100` e segue `meta.last_page`, conferindo `meta.snapshot` entre as páginas. A busca remota usa `q` e pode pesquisar o corpo sem devolvê-lo na listagem. Resumos não incluem HTML; os detalhes vêm de `GET /api/public/posts/{id}` no leitor estático ou de `GET /api/public/posts/slug/{slug}` no renderizador PHP. A API só entrega `PP` e responde `409` se o conjunto mudar durante a leitura. A listagem preserva a ordem do PENA, destaque único, filtro, busca sem acentos e paginação visual. O HTML é sanitizado no PENA e novamente na rota PHP ou no navegador, conforme o modo.

Para atualizar a cópia local usada antes da ativação da API, exporte novamente os artigos conforme `../README.md` e gere esta cópia apenas com registros `PP`. Não edite manualmente o arquivo gerado. As imagens históricas ainda dependem do domínio `pena.venezapiscinas.com.br`; valide a disponibilidade dessas URLs e os direitos de uso antes de publicar o novo repositório.

Na hospedagem Apache/PHP, `.htaccess` faz `/admin` redirecionar a um destino fixo no PENA, `/conhecimento.html` encaminhar a `/conhecimento/`, `/artigo.html?id=N` consultar o ID público e redirecionar ao slug, e `/conhecimento/<slug>` chegar a `article.php`. Defina no ambiente PHP `VENEZA_PUBLIC_API_ORIGIN=https://api.venezapiscinas.com.br` — sem credenciais e sem caminho. A página PHP consulta somente a API pública, valida ID/slug/status, não segue redirecionamentos, aplica limite de resposta e devolve `Cache-Control: no-store, private`. Título, descrição, canonical, Open Graph e JSON-LD saem no HTML inicial; título e texto são escapados e o corpo passa por sanitização DOM. Slugs de artigos já publicados ficam congelados até existir histórico de aliases aprovado. Sem API configurada, a página responde 503. A página antiga `artigo.html` continua genérica e `noindex` na prévia estática.

O cPanel ainda redireciona o domínio sem `www` para `www`, enquanto o canonical preparado é sem `www` conforme o destino solicitado. **Antes do deploy**, rever esse redirecionamento e decidir a regra única, sem criar uma regra oposta que cause loop. Confirmar PHP web/extensões, HTTPS, CORS, cache Nginx e URLs das imagens legadas. Não substituir arquivos existentes em `/public_html` sem backup e plano de retorno.

Os problemas identificados na revisão e os critérios para a futura integração estão registrados em [PENDENCIAS-PENA.md](PENDENCIAS-PENA.md).

Os testes de contrato JavaScript estão em `tests/content.test.cjs`. `tests/run-http.sh` exercita PHP/HTTP com API sintética em loopback, inclusive URL direta, status, redirecionamentos e SEO inicial; `tests/browser.mjs` confere layout, teclado, busca, mídia e sanitização em Chromium. O workflow prepara esses testes sem acessar a API real. Eles não substituem ensaio no Apache, cPanel e domínio de produção.

`tests/responsive-smoke.mjs` verifica as páginas institucionais e um artigo da prévia em 320, 375, 480, 768, 1024 e 1440 px. Confere menu por teclado, ausência de rolagem horizontal, proporção da logo, imagens locais, links e formulário do WhatsApp, sem enviar mensagens ou consultar a API real. O teste faz parte de `tests/run-browser-ci.sh` e também roda antes do deploy da ServHost.

Depois da separação do PENA, os testes usam imagens independentes: `docker build -t site-php-tests -f site/tests/PHP.Dockerfile site/tests` e `docker build -t site-browser-tests site/tests/Browser`, executados na raiz deste repositório. `.github/workflows/site-tests.yml` mantém os contratos PHP/JS, os testes em navegador e o gerador do snapshot. Não é preciso clonar o PENA para testar o site com a API sintética.
