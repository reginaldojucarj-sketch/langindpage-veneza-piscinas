(() => {
    const library = document.querySelector('[data-media-library]');
    if (!library) return;
    const picker = new URLSearchParams(window.location.search).get('picker');
    const pickerToken = typeof picker === 'string' && /^[a-f0-9]{32}$/.test(picker) ? picker : null;
    const channel = pickerToken && typeof BroadcastChannel === 'function'
        ? new BroadcastChannel(`pena:media-picker:${pickerToken}`) : null;
    if (channel) {
        channel.addEventListener('message', (event) => {
            if (event.data?.type === 'pena:media-applied') {
                library.querySelector('[data-selection-status]').textContent = 'Capa aplicada no editor. Volte à aba do artigo e confira antes de salvar.';
            }
        });
        window.addEventListener('pagehide', () => channel.close());
    }

    const uploadForm = library.querySelector('[data-media-upload]');
    if (uploadForm) {
        uploadForm.addEventListener('submit', (event) => {
            event.preventDefault();
            const status = uploadForm.querySelector('[data-upload-status]');
            const progress = uploadForm.querySelector('[data-upload-progress]');
            const button = uploadForm.querySelector('[data-upload-button]');
            const request = new XMLHttpRequest();
            request.open('POST', uploadForm.action);
            request.setRequestHeader('Accept', 'application/json');
            request.withCredentials = true;
            button.disabled = true;
            progress.hidden = false;
            progress.value = 0;
            status.textContent = 'Preparando o envio…';

            request.upload.addEventListener('progress', (progressEvent) => {
                if (!progressEvent.lengthComputable) return;
                const percent = Math.round((progressEvent.loaded / progressEvent.total) * 100);
                progress.value = percent;
                status.textContent = `Enviando: ${percent}%`;
            });
            request.addEventListener('load', () => {
                if (request.status >= 200 && request.status < 300 && request.responseURL) {
                    try {
                        const destination = new URL(request.responseURL, window.location.href);
                        if (destination.origin === window.location.origin && destination.pathname === window.location.pathname) {
                            if (pickerToken) destination.searchParams.set('picker', pickerToken);
                            window.location.assign(destination.href);
                            return;
                        }
                    } catch { /* An unexpected redirect is not used for navigation. */ }
                }

                button.disabled = false;
                const payload = (() => {
                    try { return JSON.parse(request.responseText); } catch { return null; }
                })();
                const errors = payload?.errors ? Object.values(payload.errors).flat().join(' ') : '';
                status.textContent = request.status === 419
                    ? 'Sua sessão expirou. Atualize a página e tente novamente.'
                    : (errors || 'Não foi possível enviar a imagem. Confira o arquivo e tente novamente.');
                progress.hidden = true;
            });
            request.addEventListener('error', () => {
                button.disabled = false;
                progress.hidden = true;
                status.textContent = 'Falha de conexão durante o envio. O arquivo não foi confirmado; tente novamente.';
            });
            request.send(new FormData(uploadForm));
        });
    }

    library.addEventListener('click', async (event) => {
        const selectButton = event.target.closest('[data-use-media]');
        if (selectButton) {
            const detail = {
                kind: selectButton.dataset.mediaKind,
                id: selectButton.dataset.mediaId,
                url: selectButton.dataset.mediaUrl,
                alt: selectButton.dataset.mediaAlt || '',
            };
            document.dispatchEvent(new CustomEvent('pena:media-selected', { bubbles: true, detail }));
            const output = library.querySelector('[data-selection-output]');
            output.hidden = false;
            output.querySelector('[data-selection-label]').textContent = detail.alt || 'Mídia selecionada';
            output.querySelector('[data-selection-url]').value = detail.url;
            output.querySelector('[data-selection-status]').textContent = channel
                ? 'Seleção enviada ao editor. Volte à aba do artigo e confira antes de salvar.'
                : 'Copie a URL e cole no campo de capa do artigo.';
            channel?.postMessage({ type: 'pena:media-selected', ...detail });
            output.scrollIntoView({
                behavior: window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth',
                block: 'nearest',
            });
            return;
        }

        const copyButton = event.target.closest('[data-copy-media]');
        if (copyButton) {
            const url = library.querySelector('[data-selection-url]').value;
            const status = library.querySelector('[data-selection-status]');
            try {
                await navigator.clipboard.writeText(url);
                status.textContent = 'URL copiada.';
            } catch {
                status.textContent = 'Selecione a URL e copie usando o teclado.';
                const input = library.querySelector('[data-selection-url]');
                input.focus();
                input.select();
            }
            return;
        }

        const deactivateForm = event.target.closest('[data-confirm-deactivate]');
        if (deactivateForm && !window.confirm('Desativar esta imagem? O arquivo original continuará preservado.')) {
            event.preventDefault();
        }
    });
})();
