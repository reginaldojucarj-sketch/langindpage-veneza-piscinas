# Documentação da landing Veneza Piscinas

Este índice cobre somente a landing comercial e seu leitor estático de artigos. Não contém instalação Laravel, operação do banco, manutenção da loja ou scripts do institucional.

| Preciso de… | Documento |
| --- | --- |
| Entender páginas, arquitetura e rodar localmente | [README principal](../README.md) |
| Alterar conteúdo ou interação com segurança | [Contratos e instruções do projeto](../AGENTS.md) |
| Publicar, verificar a versão e investigar falhas | [Publicação da landing](PUBLICACAO.md) |
| Conferir o que a versão estável representa | [Marco `stable-2026-10-09`](releases/stable-2026-10-09.md) |
| Ver origem, formato e dimensões de mídia | [Mídias](../assets/README.md), [produtos](../assets/images/products/README.md), [clientes](../assets/images/clients/README.md) e [parceiros](../assets/images/partners/README.md) |
| Atualizar a cópia pública dos artigos | [Fluxo de exportação e geração](../README.md#atualização-dos-artigos) |
| Entender a escolha do endereço comercial | [Pesquisa SEO](../PESQUISA-SEO.md), com suas datas e limites de evidência |

## Onde cada projeto é documentado

- Landing: este repositório; produção em [equipamentos.venezapiscinas.com.br](https://equipamentos.venezapiscinas.com.br/).
- Institucional: [repositório `veneza_site`](https://github.com/fesizw/veneza_site). É ele que referencia o PENA como submódulo.
- Painel e API: [repositório `PENA`](https://github.com/fesizw/PENA), incluindo o [guia do Swagger online](https://github.com/fesizw/PENA/blob/main/SWAGGER.md).

Não copie configurações, credenciais, dependências ou procedimentos operacionais desses outros projetos para esta landing. A loja está fora do escopo de atualização destes três repositórios.

## Manutenção da documentação

Atualize o README e o guia correspondente quando mudar uma URL, contrato, destino de publicação ou comando de teste. Registros de release são históricos: mantenha suas datas e commits; documente uma nova verificação em um novo registro, sem apresentar um resultado antigo como estado atual.

Nunca inclua senhas, tokens, dumps, respostas completas de autenticação ou dados pessoais nos exemplos. Nomes de Actions secrets podem ser documentados; seus valores não.
