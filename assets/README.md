# Imagens do site

- `images/hero/`: imagens e posters do hero. As quatro imagens fotográficas preparadas pelo script têm 1200 × 800; os dois posters de vídeos adicionados em setembro de 2026 têm 960 × 720.
- `images/brand/`: logotipo e imagem de referência do Pingo (`pingo-mascote.png`).
- `images/clients/`: logos dos clientes exibidos entre os depoimentos e o FAQ; fontes em [images/clients/README.md](images/clients/README.md).
- `images/partners/`: logos das marcas parceiras; fontes em [images/partners/README.md](images/partners/README.md).
- `images/products/`: fotos de referência dos equipamentos e da iluminação exibidas no modal da seção “Nossa linha”; as origens estão em `images/products/README.md`.
- `images/services/`: 19 imagens finais em JPEG 1280 × 960 usadas no carrossel e no modal de Projeto Hidráulico.
- `images/backgrounds/`: textura de fundo em uso.
- `sources/hero/`: fotos originais, edições em IA, versões anteriores e originais locais dos vídeos do hero.
- `sources/services/originals/`: arquivos originais recebidos para a galeria de serviços, preservados antes do tratamento.
- `sources/products/originals/`: arquivos originais recebidos para as galerias de produtos e iluminação.
- `sources/backgrounds/` e `sources/ui/`: materiais de referência fora de uso na página.

Para recriar as quatro imagens fotográficas no Windows, execute `powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\scripts\prepare-hero-images.ps1` a partir da raiz do projeto.

Os vídeos do hero foram preparados com o FFmpeg 7.1 incluído no Format Factory 5.22. As saídas não contêm áudio, usam VP9 no WebM e H.264 no MP4 e ficam abaixo de 500 KB por arquivo. `piscina-vista-ilha-pingo`, `piscina-iluminada-pingo` e `piscina-aquecida-pingo` têm 480 × 360; `piscina-hotel-pingo` preserva 640 × 360. Os dois posters mais recentes foram extraídos de quadros nítidos próximos ao final de cada animação.

## Edição em IA

O arquivo `sources/hero/piscina-vista-ilha-ia.png` foi gerado com a ferramenta integrada de edição de imagens a partir de `sources/hero/piscina-vista-ilha-original.jpg`. Ele amplia a foto vertical para um enquadramento horizontal; as laterais são reconstruídas, então a imagem final não é um registro fotográfico exato dessas áreas.

Prompt usado:

> Use case: precise-object-edit. Asset type: photographic slide for a swimming-pool website carousel. Input image 1 is the edit target, a real vertical photo of an infinity pool overlooking the sea and an island. Primary request: enhance clarity and resolution, and naturally extend the photo to a horizontal 3:2 landscape composition suitable for a carousel. Keep the existing pool, island, horizon, foliage, sky, camera viewpoint, and their relative positions as faithful as possible to the original; add only plausible continuation at the left and right edges. Maintain a restrained natural blue color balance and realistic photographic texture, with no over-sharpening or dramatic filter. No people, buildings, text, logos, watermarks, or invented focal objects.

As fotos de Santorini e da casa nas montanhas receberam edição de iluminação para parecerem ensolaradas ao meio-dia. Essas versões de base, antes da inclusão do Pingo, estão em `sources/hero/` com o sufixo `-sem-pingo.jpg`. As edições em IA podem alterar detalhes da cena; os originais também foram preservados nessa pasta.

Depois, o Pingo foi integrado às quatro piscinas com a ferramenta integrada de edição de imagens. Em uma segunda edição, a nadadeira que apontava para fora da cena foi substituída por um gesto aberto de convite para entrar na água. Os arquivos finais em `images/hero/` terminam em `-pingo.jpg`; as fontes atuais terminam em `-pingo-convite-ia.png`, e as versões anteriores permanecem em `sources/hero/`. Os prompts usados estão em [sources/hero/PINGO-PROMPTS.md](sources/hero/PINGO-PROMPTS.md). Como a integração é gerada por IA, detalhes do mascote e dos cenários podem variar em relação às referências.

Prompt de Santorini:

> Use case: lighting-weather. Asset type: photorealistic pool carousel slide. Input image 1 is the edit target. Change only the time of day and lighting from sunset to a bright clear summer midday with the sun high overhead: clean blue sky, blue sea, luminous turquoise pool water, neutral white architecture, crisp natural daylight and short shadows. Remove the visible low sunset sun and pink/orange dusk glow. Preserve the same infinity pool, curved pool edge, rock formation, islands, sea horizon, white terrace, loungers, camera viewpoint and composition as closely as possible. Keep realistic photographic texture and restrained color, matching other bright summer pool photographs. Do not add, remove or move architectural objects. No people, text, logos, watermark or dramatic filter.

Prompt da casa nas montanhas:

> Use case: lighting-weather. Asset type: photorealistic pool carousel slide. Input image 1 is the edit target. Change only the lighting and atmosphere to a clear, bright summer midday with the sun high overhead: vivid but natural blue sky, clean daylight on the infinity pool, bright aqua water, sunlit terrace and mountains, short realistic shadows. Remove the subdued late-day haze and gloomy shaded feeling. Preserve the house architecture, roof and railings, the pool outline and steps, loungers, mountains, camera viewpoint and composition as closely as possible. Keep realistic photographic texture and restrained color, consistent with premium bright summer pool photographs. Do not add, remove or move architectural objects. No people, text, logos, watermark or dramatic filter.
