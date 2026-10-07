(() => {
    const link = document.querySelector('[data-open-media-library]');
    const media = document.getElementById('media_id');
    const cover = document.getElementById('cover_url');
    const status = document.querySelector('[data-media-pick-status]');
    if (!link || !media || !cover || !status) return;

    const channels = new Set();
    const uuid = /^[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}$/i;

    function safeLegacyUrl(value) {
        if (typeof value !== 'string' || value.length > 200 || /[\x00-\x20\\]/.test(value)) return false;
        let parsed;
        try { parsed = new URL(value); } catch { return false; }
        return !parsed.username && !parsed.password && !parsed.hash &&
            (parsed.protocol === 'https:' || (parsed.protocol === 'http:' && parsed.origin === window.location.origin));
    }

    function receive(channel, event) {
        const choice = event.data;
        if (!choice || choice.type !== 'pena:media-selected') return;

        if (choice.kind === 'managed' && typeof choice.id === 'string' && uuid.test(choice.id)) {
            if (!Array.from(media.options).some((option) => option.value === choice.id)) {
                const option = document.createElement('option');
                option.value = choice.id;
                option.textContent = `Imagem escolhida: ${typeof choice.alt === 'string' ? choice.alt.slice(0, 255) : choice.id}`;
                media.append(option);
            }
            media.value = choice.id;
            media.dispatchEvent(new Event('change', { bubbles: true }));
            status.textContent = 'Imagem da biblioteca selecionada. Ela terá prioridade sobre a URL da capa; confira antes de salvar.';
        } else if (choice.kind === 'legacy' && safeLegacyUrl(choice.url)) {
            media.value = '';
            media.dispatchEvent(new Event('change', { bubbles: true }));
            cover.value = choice.url;
            cover.dispatchEvent(new Event('input', { bubbles: true }));
            status.textContent = 'URL da imagem antiga aplicada à capa. Confira antes de salvar.';
        } else {
            status.textContent = 'A seleção recebida não é válida. Nenhuma capa foi alterada.';
            return;
        }

        channel.postMessage({ type: 'pena:media-applied' });
        channel.close();
        channels.delete(channel);
    }

    link.addEventListener('click', (event) => {
        if (event.defaultPrevented || event.button !== 0 || event.altKey || event.ctrlKey || event.metaKey || event.shiftKey ||
            typeof BroadcastChannel !== 'function' || !window.crypto?.getRandomValues) return;

        const url = new URL(link.href, window.location.href);
        if (url.origin !== window.location.origin) return;

        const bytes = window.crypto.getRandomValues(new Uint8Array(16));
        const token = Array.from(bytes, (byte) => byte.toString(16).padStart(2, '0')).join('');
        let channel;
        try { channel = new BroadcastChannel(`pena:media-picker:${token}`); } catch { return; }
        channel.addEventListener('message', (message) => receive(channel, message));
        channels.add(channel);
        url.searchParams.set('picker', token);
        link.href = url.href;
        status.textContent = 'Biblioteca aberta em nova aba. Escolha a capa lá e volte para conferir aqui.';
    });

    window.addEventListener('pagehide', () => {
        channels.forEach((channel) => channel.close());
        channels.clear();
    });
})();
