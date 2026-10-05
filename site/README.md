# Site institucional da Veneza Piscinas — versão provisória

Esta pasta é um protótipo autônomo para o futuro repositório do site institucional. A landing page existente na raiz do repositório não foi alterada. O site usa HTML, CSS e JavaScript puro, sem build ou dependências.

No repositório atual, o GitHub Actions publica a raiz inteira em cada push para `main`. Portanto, ao versionar esta pasta na `main`, o protótipo também ficará acessível no subcaminho `/site/` do GitHub Pages existente. Isso não cria o novo repositório nem substitui a landing principal.

## Páginas

| Arquivo | Conteúdo |
| --- | --- |
| `index.html` | Home, necessidades do visitante, projeto hidráulico e entrada para as demais páginas. |
| `solucoes.html` | Soluções organizadas pela necessidade da piscina. |
| `produtos.html` | Vitrine consultiva das linhas atualmente apresentadas na landing. |
| `conhecimento.html` | Central de Conhecimento com busca, assuntos, listagem de artigos e orientações da landing. |
| `artigo.html?id=<id>` | Leitura individual de cada artigo publicado. |
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

Se o Python não estiver disponível, qualquer servidor estático serve. Ao levar para o novo repositório, copie **o conteúdo de `site/`** para a raiz do novo projeto, mantendo os caminhos relativos.

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
- Ajustar a infraestrutura de publicação do novo repositório. Esta pasta não contém workflow de deploy.

## Arquivos principais

`assets/css/site.css` centraliza o visual e a responsividade. `assets/js/site.js` controla o menu no celular e o formulário de contato. Os dados do WhatsApp são montados no navegador; o site não armazena as respostas do formulário.

## Central de Conhecimento e futura API do PENA

O nome da seção é **Central de Conhecimento** porque o acervo vai além de piscinas: inclui tratamento da água, equipamentos, aquecimento, banheiras, saunas e segurança. A página `conhecimento.html` preserva as seis orientações curtas anteriores e acrescenta um artigo em destaque, busca, filtro por assunto e cartões que levam a `artigo.html?id=<id>`. O destaque é o primeiro item da ordem recebida, sem repetição na lista. A cópia local em `assets/data/posts-data.js` foi gerada do snapshot da landing de 17/09/2026: 152 registros com estado `PP`. Os outros 32 registros (`PO` e `PE`, incluindo testes) continuam intactos no snapshot original da landing, mas não são exibidos aqui. Nenhuma base de dados foi alterada.

`assets/js/config.js` centraliza `apiBaseUrl` (vazio por padrão) e a base das mídias legadas. Quando a API pública estiver disponível, configure ali sua origem HTTPS, sem `/` final. `assets/js/content.js` consulta `GET {apiBaseUrl}/api/public/posts` (array JSON completo, ou `{ "data": [...] }`) e `GET {apiBaseUrl}/api/public/posts/{id}` (objeto JSON, ou `{ "data": {...} }`). Em falha de rede ou resposta inválida, a interface usa o snapshot local. A API deverá permitir CORS para a origem do site, retornar somente artigos públicos e fornecer `id`, `title`, `html`, `status`, `published_at`, `created_at`, `category`, `categories`, `image`, `image_name`, `author`, `description` e `snippet`. O campo opcional `sort_order` controla a ordem crescente; artigos sem ordem definida vêm depois, por data mais recente. O HTML é sanitizado no navegador; essa proteção não substitui a validação no servidor.

Para atualizar o fallback, exporte novamente os artigos conforme `../README.md` e gere esta cópia apenas com registros `PP`. Não edite manualmente o arquivo gerado. As imagens históricas ainda dependem do domínio `pena.venezapiscinas.com.br`; valide a disponibilidade dessas URLs e os direitos de uso antes de publicar o novo repositório.

Como `artigo.html` é renderizado por JavaScript a partir de `?id=`, título e metadados de cada artigo são atualizados no navegador. Robôs de redes sociais que não executam JavaScript podem ver apenas os metadados genéricos. Para SEO e prévias sociais completas na publicação definitiva, a integração com o PENA deverá gerar HTML pré-renderizado ou páginas estáticas por artigo.

Os problemas identificados na revisão e os critérios para a futura integração estão registrados em [PENDENCIAS-PENA.md](PENDENCIAS-PENA.md).
