# Veneza Piscinas

Landing page da Veneza Piscinas, com foco na venda de equipamentos para piscinas em Recife e região metropolitana.

## Produtos em destaque

1. Filtros e motobombas
2. Aquecedores de piscinas
3. Gerador de cloro
4. Gerador de ozônio
5. Iluminação LED e refletores
6. Projeto hidráulico

## Recursos

- Interface responsiva para desktop e celular
- Visual glassmorphism sobre textura de água
- Catálogo com filtros por categoria
- Modal com marcas e equipamentos
- FAQ acessível e interativo
- Galeria de projetos
- Links diretos para orçamento pelo WhatsApp
- Página estática, sem dependências de build

## Como visualizar localmente

Abra o `index.html` diretamente no navegador ou sirva a pasta por um servidor HTTP local. Para testar em outro dispositivo conectado à mesma rede, use o endereço IP local da máquina e a porta do servidor.

## Estrutura

- `index.html`: página principal e interações
- `posts.html`: arquivo pesquisável de artigos do banco `veneza_pena`
- `assets/data/posts-data.js`: cópia estática dos 184 posts e metadados de categorias, autores e imagens
- `assets/images`: logo, imagens de fundo, hero e produtos
- `assets/images/projects`: fotos e capas otimizadas para a galeria de projetos
- `assets/videos/projects`: vídeos da galeria em MP4, sem faixa de áudio
- `assets/sources/projects`: fotos originais da galeria; vídeos originais com áudio ficam apenas localmente
- `scripts`: arquivos auxiliares do projeto

## Arquivo de artigos

A página `posts.html` mostra todos os registros encontrados em `POST_pena` na extração de 17/09/2026, inclusive os estados `PP`, `PO` e `PE`. Os textos vieram de uma consulta com `LEFT JOIN` nas tabelas relacionadas a artigos: `AUTOR_pena`, `PESSOA_pena`, `IMAGENS_pena`, `CATEGORIA_pena` e `CATEGORIA_POST_pena`. Apenas campos editoriais foram exportados; credenciais e dados das tabelas de clientes, e-mail e logs não entram na página.

O GitHub Pages publica uma cópia estática. Para atualizar os artigos, execute a consulta em `scripts/export-posts.sql` no banco, salve o resultado em JSONL e gere novamente o arquivo de dados com `scripts/build-posts-data.ps1 -Source <arquivo.jsonl> -Destination assets/data/posts-data.js`.
