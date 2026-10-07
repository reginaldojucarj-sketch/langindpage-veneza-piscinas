// A página PHP pode fornecer a origem pública para uma homologação HTTPS.
// A prévia estática fora do domínio definitivo continua usando a cópia local.
const venezaLiveHost = location.protocol === 'https:' && /^(www\.)?venezapiscinas\.com\.br$/i.test(location.hostname);
const venezaApiMeta = typeof document === 'undefined' ? null : document.querySelector('meta[name="veneza-public-api-origin"]');
const venezaConfiguredApi = venezaApiMeta && /^https:\/\/[^/?#]+$/.test(venezaApiMeta.content) ? venezaApiMeta.content : '';
window.VENEZA_CONTENT_CONFIG = Object.freeze({
  apiBaseUrl: venezaConfiguredApi || (venezaLiveHost ? 'https://api.venezapiscinas.com.br' : ''),
  friendlyArticleBase: venezaConfiguredApi || venezaLiveHost ? '/conhecimento/' : '',
  legacyMediaBaseUrl: 'https://pena.venezapiscinas.com.br/'
});
