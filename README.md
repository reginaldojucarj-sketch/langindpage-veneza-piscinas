# Veneza Piscinas

Landing page da Veneza Piscinas focada na venda de produtos selecionados para piscinas em Recife e região metropolitana. O objetivo é apresentar essas soluções e converter o interesse do visitante em pedidos de orçamento pelo WhatsApp.

Os destaques são filtros e motobombas, aquecedores, geradores de cloro e ozônio e iluminação LED, com serviços complementares de projeto hidráulico e revestimento em manta armada. Projetos realizados, depoimentos e perguntas frequentes ajudam o visitante a avaliar as soluções e avançar para o contato comercial.

Desenvolvida com **HTML, CSS e JavaScript puro**, sem framework, instalação de pacotes ou etapa de build. Os arquivos podem ser servidos diretamente por uma hospedagem estática.

Este repositório contém somente a landing comercial e seu leitor estático de artigos. O [site institucional](https://github.com/fesizw/veneza_site) e o [painel/API PENA](https://github.com/fesizw/PENA) têm repositórios independentes. O GitHub Pages publica apenas esta landing; site institucional e PENA são instalados separadamente na ServHost.

O institucional referencia o PENA como submódulo Git em seu próprio diretório `PENA/`, fixado em um commit publicado. Esse vínculo existe somente em `veneza_site`: esta landing não contém submódulos nem código do institucional ou do Laravel.

## Páginas e funcionalidades

| Página | Conteúdo |
| --- | --- |
| [index.html](index.html) | Landing page de produtos selecionados, com serviços complementares, projetos, depoimentos, FAQ e chamadas para orçamento. |
| [posts.html](posts.html) | Página complementar de artigos com busca textual, filtro por assunto e leitura em modal. |
| [font-showcase.html](font-showcase.html) | Página auxiliar para comparação visual de fontes. |

Na landing page:

- Layout responsivo, painéis translúcidos e fundo com textura de água.
- Carrossel de abertura com uma foto e quatro vídeos do mascote Pingo, cada vídeo com poster e formatos WebM/MP4.
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

O hero atual usa uma dessas imagens como slide estático e as demais imagens necessárias como posters. Os dois posters mais recentes têm 960 × 720. Os quatro vídeos possuem WebM VP9 e MP4 H.264, sem áudio, com resolução entre 480 × 360 e 640 × 360 e menos de 500 KB por arquivo. Eles são carregados conforme a navegação, tocam uma vez e avançam para o próximo slide.

Os vídeos publicados do hero e da galeria de projetos usam versões sem áudio. Os originais do hero e os arquivos com sufixo `-original.mp4` nas pastas de fontes são ignorados pelo Git; eles permanecem disponíveis localmente para novas conversões.

## Atualização dos artigos

O arquivo público `assets/data/posts-data.js` contém **152 artigos publicados (`PP`)** da extração de **17/09/2026** do banco `veneza_pena`. A base da época tinha também 26 `PO` e 6 `PE`; esses registros não devem entrar no snapshot nem aparecer em `posts.html`. Uma cópia antiga desses dados existiu no histórico do repositório: retirar registros do arquivo atual não apaga versões anteriores do Git ou artefatos já publicados.

O navegador lê `window.VENEZA_POSTS` desse arquivo, sem consultar o banco. Imagens e links relativos dos artigos são resolvidos a partir de `https://pena.venezapiscinas.com.br/`, conforme definido em `assets/js/posts.js`.

Para gerar uma nova cópia:

1. Execute [scripts/export-posts.sql](scripts/export-posts.sql) no banco de origem. A consulta exporta **somente `PP`** e combina `POST_pena` com as tabelas relacionadas de autores, pessoas, imagens e categorias.
2. Salve a coluna `article` em um arquivo **JSONL**: um objeto JSON por linha, sem cabeçalho ou formatação de tabela.
3. Na raiz do projeto, execute o conversor com PowerShell. O destino deve ser relativo à raiz:

   ```powershell
   .\scripts\build-posts-data.ps1 -Source "C:\caminho\artigos.jsonl" -Destination "assets/data/posts-data.js"
   ```

4. Confira acentos, quantidade de artigos, busca, assuntos, imagens e abertura dos textos em `posts.html`.
5. Versione o arquivo gerado junto das alterações necessárias. Se a extração mudar, atualize a data e as contagens nesta seção.

O conversor seleciona os campos editoriais previstos, mantém apenas `PP` mesmo se a entrada contiver outros estados, rejeita ausência de publicados ou IDs duplicados e grava o JavaScript em UTF-8. Antes de publicar, execute `node --test tests/public-posts.test.cjs` e `powershell -NoProfile -ExecutionPolicy Bypass -File tests/build-posts-data.test.ps1`; o workflow de Pages também executa esses testes. A consulta não exporta credenciais nem registros das tabelas de clientes, e-mail ou logs.

## Publicação

A URL comercial preferida da landing é `https://equipamentos.venezapiscinas.com.br/`,
no document root `/home/veneza/public_html/equipamentos.venezapiscinas.com.br`,
separado do site institucional, da loja e do PENA.
O endereço anterior, `https://orcamento.venezapiscinas.com.br/`, deve manter
redirecionamentos permanentes 301 para os mesmos caminhos no novo endereço por
pelo menos um ano. Instale o `.htaccess` também na pasta antiga somente depois de
validar DNS, certificado e conteúdo da nova origem. Não remova o domínio antigo.
Veja a metodologia e os limites da escolha em [PESQUISA-SEO.md](PESQUISA-SEO.md).
O workflow [deploy-servhost.yml](.github/workflows/deploy-servhost.yml) publica nela
a cada push na `main`, ou por execução manual na própria `main`. O workflow de Pages
continua separado. Ambos são somente da landing; não instalam site institucional/PENA.

Configure neste repositório os Actions secrets `SERVHOST_FTP_USERNAME` e
`SERVHOST_FTP_PASSWORD`. A conta deve alcançar `/home/veneza`, pois o destino FTP é
fixo em `/public_html/equipamentos.venezapiscinas.com.br`. A variável opcional
`SERVHOST_FTP_HOST` substitui `rv2.servhost.com.br` somente por um hostname com TLS
válido. Os secrets do institucional não são compartilhados automaticamente.

O Action testa artigos públicos, SEO, JavaScript, gerador e publicação isolada antes
do envio. Publica somente as três páginas HTML, `robots.txt`, `sitemap.xml`, `.htaccess`
e assets públicos **versionados**, excluindo documentação, fontes, scripts, testes,
originais e arquivos ignorados. Usa uma conexão FTPS explícita na porta 21, canais
criptografados, certificado validado e nenhuma tentativa automática. Confere tamanho
antes de promover cada arquivo temporário e SHA-256 da home, artigos, CSS, logo,
robots e sitemap em HTTPS. A home é enviada por último. Não há exclusões remotas nem
alteração da loja, mídias legadas, institucional ou PENA; não é uma transação única
entre todos os arquivos. O resumo de sucesso registra o commit e a URL conferidos.

O document root precisa existir antes da primeira execução. Falha de rede/blacklist
ou secrets ausentes deixa o Action em erro, nunca como publicação concluída. Confira
o resultado em Actions; criar o workflow não comprova que o servidor foi atualizado.

As páginas principal e de artigos indicam essa origem em seus canonicals. O sitemap
lista somente essas duas páginas; a comparação de fontes tem `noindex`. O `.htaccess`
serve apenas arquivos estáticos, desativa listagem de diretórios e normaliza HTTPS,
`www` e `/index.html` na ServHost. O GitHub Pages não executa regras Apache. O nome do
subdomínio não garante melhor ranking; sitemap e canonical ajudam os buscadores a
descobrir e consolidar a versão preferida, sem garantir indexação. A solicitação de
indexação no Search Console depende de uma propriedade verificada pelo responsável.

O workflow [deploy-pages.yml](.github/workflows/deploy-pages.yml) publica o site no **GitHub Pages** em push para `main` ou por acionamento manual de um commit da própria `main`. Execuções manuais em outras branches não publicam.

Ele faz checkout do repositório, configura o Pages e prepara um diretório novo com `scripts/deploy-servhost-landing.py --stage-pages _pages`, sem rede nem compilação. Reutiliza a mesma seleção de arquivos públicos **versionados** do FTPS, exceto `.htaccess`: inclui `index.html`, `posts.html`, `font-showcase.html`, `robots.txt`, `sitemap.xml` e recursos aprovados de `assets/{css,data,images,js,videos}`. READMEs de mídia, fontes, originais, arquivos ignorados/não versionados e ferramentas não entram no artefato. Um destino já existente é recusado, impedindo resíduos de outra preparação. Site institucional e PENA residem em outros repositórios e não integram o artefato. O repositório precisa estar configurado para publicar o Pages por **GitHub Actions**. A URL da publicação aparece no ambiente `github-pages` da execução.

`assets/sources/` não entra nos novos artefatos do Pages. Isso não remove cópias de artefatos anteriores nem arquivos do histórico Git; uma limpeza de histórico/retenção exigiria decisão separada.

## Checklist de revisão

Antes de publicar mudanças, confira no navegador:

- Layout em desktop e celular, incluindo menu e ausência de rolagem horizontal inesperada.
- Carrosséis, modais de produtos, galeria ampliada e reprodução dos depoimentos.
- Navegação por teclado, fechamento dos modais com `Esc` e abertura das respostas do FAQ.
- Número de destino e mensagens dos links de WhatsApp.
- Busca, filtro por assunto e leitura dos artigos em `posts.html`.
- Console e painel de rede sem erros de JavaScript ou arquivos locais ausentes.

A landing continua exigindo conferência manual das interações; há um teste automatizado para impedir a publicação de artigos não públicos no seu snapshot. Os testes e a publicação FTPS do site institucional estão em [veneza_site](https://github.com/fesizw/veneza_site). Os testes do painel/API estão no [repositório PENA](https://github.com/fesizw/PENA). O script de exportação e o gerador permanecem aqui para atualizar somente o leitor da landing; a separação preservou o histórico e não alterou o servidor.
