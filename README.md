# Veneza Piscinas

Landing page da Veneza Piscinas focada na venda de produtos selecionados para piscinas em Recife e região metropolitana. O objetivo é apresentar essas soluções e converter o interesse do visitante em pedidos de orçamento pelo WhatsApp.

Os destaques são filtros e motobombas, aquecedores, geradores de cloro e ozônio e iluminação LED, com serviços complementares de projeto hidráulico e revestimento em manta armada. Projetos realizados, depoimentos e perguntas frequentes ajudam o visitante a avaliar as soluções e avançar para o contato comercial.

Desenvolvida com **HTML, CSS e JavaScript puro**, sem framework, instalação de pacotes ou etapa de build. Os arquivos podem ser servidos diretamente por uma hospedagem estática.

## Páginas e funcionalidades

| Página | Conteúdo |
| --- | --- |
| [index.html](index.html) | Landing page de produtos selecionados, com serviços complementares, projetos, depoimentos, FAQ e chamadas para orçamento. |
| [posts.html](posts.html) | Página complementar de artigos com busca textual, filtro por assunto e leitura em modal. |
| [font-showcase.html](font-showcase.html) | Página auxiliar para comparação visual de fontes. |

Na landing page:

- Layout responsivo, painéis translúcidos e fundo com textura de água.
- Carrossel de abertura com fotos e vídeos do mascote Pingo.
- Destaques para filtros e motobombas, aquecedores, geradores de cloro e ozônio, iluminação LED e projeto hidráulico.
- Modais de equipamentos e serviços, incluindo revestimento em manta armada, com mídias e mensagens de orçamento específicas.
- Galeria de projetos com fotos, vídeos, visualização ampliada e navegação por gestos.
- Depoimentos em vídeo com controles de reprodução.
- Seções de clientes e marcas parceiras entre os depoimentos e o FAQ, com logos armazenados no projeto.
- FAQ expansível, menu compacto no celular e botão de retorno ao topo.
- Links de orçamento pelo WhatsApp e acesso às redes sociais.

## Como executar localmente

Para uma visualização rápida, abra `index.html` no navegador. Para conferir o comportamento em uma hospedagem, use um servidor HTTP local.

Com **Python 3 instalado**, execute na raiz do repositório:

```powershell
python -m http.server 8000
```

No Windows, caso o Python esteja disponível pelo launcher, use `py -m http.server 8000`.

Abra:

- Página principal: http://localhost:8000/
- Artigos: http://localhost:8000/posts.html
- Comparação de fontes: http://localhost:8000/font-showcase.html

Encerre o servidor com `Ctrl+C`. Para testar no celular, conecte os dispositivos à mesma rede e acesse `http://<IP-local-do-computador>:8000`; o firewall precisa permitir a conexão.

O Python é apenas uma opção de servidor para desenvolvimento. O site publicado não depende dele. Fontes do Google Fonts, imagens externas dos artigos e destinos como WhatsApp dependem de acesso à internet.

## Estrutura do projeto

```text
.
├── index.html                   # Conteúdo e estrutura da página principal
├── posts.html                   # Estrutura e estilos do arquivo de artigos
├── font-showcase.html           # Comparação de fontes
├── assets/
│   ├── css/styles.css           # Estilos da página principal e responsividade
│   ├── js/
│   │   ├── carousels.js         # Carrosséis da página principal
│   │   ├── catalog-faq.js       # FAQ e lógica de filtros de catálogo
│   │   ├── product-modal.js     # Galerias, modais e orçamento por solução
│   │   ├── project-gallery.js   # Galeria ampliada, zoom e gestos
│   │   ├── testimonials.js      # Controles dos vídeos de depoimentos
│   │   ├── site-ui.js           # Menu, retorno ao topo e efeitos de entrada
│   │   └── posts.js             # Busca, categorias e leitor de artigos
│   ├── data/posts-data.js       # Cópia estática dos artigos
│   ├── images/                 # Logo, fundos, produtos e demais imagens finais
│   ├── videos/                 # Vídeos usados nas páginas
│   └── sources/                # Originais e referências das mídias
├── scripts/
│   ├── export-posts.sql         # Consulta de exportação dos artigos
│   ├── build-posts-data.ps1     # Conversão de JSONL para dados do navegador
│   └── prepare-hero-images.ps1  # Preparação das imagens de abertura no Windows
└── .github/workflows/
    └── deploy-pages.yml        # Publicação no GitHub Pages
```

A lógica de filtros de catálogo permanece em `catalog-faq.js`, mas a página principal atual não contém os controles desse catálogo. O filtro por assunto está disponível em `posts.html`.

## Como atualizar o conteúdo

| Alteração | Onde editar |
| --- | --- |
| Textos, produtos em destaque, FAQ e contatos | `index.html` |
| Cores, tipografia, espaçamento e layout responsivo | `assets/css/styles.css` |
| Equipamentos, mídias e mensagens dos modais | `assets/js/product-modal.js` e marcação relacionada em `index.html` |
| Fotos e vídeos dos projetos | Marcação da galeria em `index.html` e arquivos em `assets/images/projects/` e `assets/videos/projects/` |
| Depoimentos | Marcação em `index.html` e mídias correspondentes |
| Clientes e marcas parceiras | Seções `#clientes` e `#parceiros` em `index.html`; logos em `assets/images/clients/` e `assets/images/partners/` |
| Busca e exibição de artigos | `assets/js/posts.js`; estilos em `posts.html` |

Ao alterar o telefone de atendimento, revise tanto os links `wa.me` em `index.html` quanto o número usado em `assets/js/product-modal.js`.

Mantenha os caminhos relativos para que os arquivos funcionem também quando o site estiver hospedado em um subdiretório. Ao substituir mídias, atualize textos alternativos, legendas e imagens de capa dos vídeos quando necessário.

### Imagens e vídeos

Consulte [a documentação das mídias](assets/README.md) para origens, dimensões e detalhes das edições com IA, e [as referências dos produtos](assets/images/products/README.md) para a procedência das imagens dos equipamentos.

As fontes dos logos estão documentadas em [clientes](assets/images/clients/README.md) e [marcas parceiras](assets/images/partners/README.md). As seções usam arquivos locais, sem depender dos sites externos para carregar as imagens. As listas de clientes e parceiros foram fornecidas pela direção da Veneza.

Para recriar as quatro imagens de abertura em JPEG de 1200 × 800, execute na raiz do projeto, no **Windows com Windows PowerShell**:

```powershell
powershell.exe -NoProfile -File .\scripts\prepare-hero-images.ps1
```

O script usa componentes de imagem do Windows e os originais de `assets/sources/hero/`. Ele sobrescreve os JPEGs correspondentes em `assets/images/hero/`.

Os vídeos publicados da galeria de projetos usam versões sem áudio. Os vídeos originais com sufixo `-original.mp4` nas pastas `assets/sources/projects/` e `assets/sources/testimonials/` são ignorados pelo Git.

## Atualização dos artigos

O arquivo `assets/data/posts-data.js` contém **184 artigos** da extração de **17/09/2026** do banco `veneza_pena`. A cópia inclui os estados `PP` (152), `PO` (26) e `PE` (6); a página não restringe a exibição por estado editorial.

O navegador lê `window.VENEZA_POSTS` desse arquivo, sem consultar o banco. Imagens e links relativos dos artigos são resolvidos a partir de `https://pena.venezapiscinas.com.br/`, conforme definido em `assets/js/posts.js`.

Para gerar uma nova cópia:

1. Execute [scripts/export-posts.sql](scripts/export-posts.sql) no banco de origem. A consulta combina `POST_pena` com as tabelas relacionadas de autores, pessoas, imagens e categorias.
2. Salve a coluna `article` em um arquivo **JSONL**: um objeto JSON por linha, sem cabeçalho ou formatação de tabela.
3. Na raiz do projeto, execute o conversor com PowerShell. O destino deve ser relativo à raiz:

   ```powershell
   .\scripts\build-posts-data.ps1 -Source "C:\caminho\artigos.jsonl" -Destination "assets/data/posts-data.js"
   ```

4. Confira acentos, quantidade de artigos, busca, assuntos, imagens e abertura dos textos em `posts.html`.
5. Versione o arquivo gerado junto das alterações necessárias. Se a extração mudar, atualize a data e as contagens nesta seção.

O conversor seleciona os campos editoriais previstos, rejeita uma exportação vazia ou com IDs duplicados e grava o JavaScript em UTF-8. A consulta não exporta credenciais nem registros das tabelas de clientes, e-mail ou logs.

## Publicação

O workflow [deploy-pages.yml](.github/workflows/deploy-pages.yml) publica o site no **GitHub Pages** quando há um push na branch `main` ou quando é acionado manualmente por `workflow_dispatch`.

Ele faz checkout do repositório, configura o Pages, envia a raiz como artefato e realiza o deploy, sem compilação. O repositório precisa estar configurado para publicar o Pages por **GitHub Actions**. A URL da publicação aparece no ambiente `github-pages` da execução.

Como o artefato usa a raiz do repositório, arquivos versionados em `assets/sources/` também entram na publicação.

## Checklist de revisão

Antes de publicar mudanças, confira no navegador:

- Layout em desktop e celular, incluindo menu e ausência de rolagem horizontal inesperada.
- Carrosséis, modais de produtos, galeria ampliada e reprodução dos depoimentos.
- Navegação por teclado, fechamento dos modais com `Esc` e abertura das respostas do FAQ.
- Número de destino e mensagens dos links de WhatsApp.
- Busca, filtro por assunto e leitura dos artigos em `posts.html`.
- Console e painel de rede sem erros de JavaScript ou arquivos locais ausentes.

O repositório não possui uma suíte de testes automatizados configurada; essa conferência é manual.
