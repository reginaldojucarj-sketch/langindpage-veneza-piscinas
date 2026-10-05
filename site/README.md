# Site institucional da Veneza Piscinas — versão provisória

Esta pasta é um protótipo autônomo para o futuro repositório do site institucional. A landing page existente na raiz do repositório não foi alterada. O site usa HTML, CSS e JavaScript puro, sem build ou dependências.

No repositório atual, o GitHub Actions publica a raiz inteira em cada push para `main`. Portanto, ao versionar esta pasta na `main`, o protótipo também ficará acessível no subcaminho `/site/` do GitHub Pages existente. Isso não cria o novo repositório nem substitui a landing principal.

## Páginas

| Arquivo | Conteúdo |
| --- | --- |
| `index.html` | Home, necessidades do visitante, projeto hidráulico e entrada para as demais páginas. |
| `solucoes.html` | Soluções organizadas pela necessidade da piscina. |
| `produtos.html` | Vitrine consultiva das linhas atualmente apresentadas na landing. |
| `conhecimento.html` | Central inicial com explicações derivadas da FAQ da landing. |
| `projetos.html` | Seleção de projetos e imagens já publicados na landing. |
| `veneza.html` | Apresentação institucional, diferenciais, clientes e parceiros. |
| `contato.html` | Dados de contato e formulário que prepara uma mensagem para o WhatsApp. |

As imagens usadas pelo protótipo foram copiadas para `assets/images/`. Nenhuma página depende de arquivos fora desta pasta. A única dependência visual externa é o Google Fonts; há fontes de reserva locais.

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
- Decidir se os 184 artigos do antigo `posts.html` serão migrados. Esta versão traz apenas uma seleção inicial de conteúdo, sem copiar a base histórica.
- Se forem adicionadas calculadoras ou recomendações automáticas, validar fórmulas e limites com um profissional responsável. O formulário atual apenas organiza uma mensagem para atendimento humano.
- Ajustar a infraestrutura de publicação do novo repositório. Esta pasta não contém workflow de deploy.

## Arquivos principais

`assets/css/site.css` centraliza o visual e a responsividade. `assets/js/site.js` controla o menu no celular e o formulário de contato. Os dados do WhatsApp são montados no navegador; o site não armazena as respostas do formulário.
