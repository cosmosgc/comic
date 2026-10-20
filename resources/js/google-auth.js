let googleScriptPromise = null;

function loadGoogleScript() {
    if (window.google?.accounts?.id) {
        return Promise.resolve();
    }

    if (googleScriptPromise !== null) {
        return googleScriptPromise;
    }

    googleScriptPromise = new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = 'https://accounts.google.com/gsi/client';
        script.async = true;
        script.defer = true;
        script.onload = () => resolve();
        script.onerror = () => reject(new Error('Google Identity Services failed to load.'));
        document.head.appendChild(script);
    });

    return googleScriptPromise;
}

function submitGoogleCredential(response) {
    const form = document.querySelector('[data-google-credential-form]');

    if (!form || !response || typeof response.credential !== 'string') {
        return;
    }

    const input = form.querySelector('input[name="credential"]');

    if (!input) {
        return;
    }

    input.value = response.credential;
    form.submit();
}

function showGoogleError(container, message) {
    const form = container.closest('[data-google-credential-form]');
    const error = form?.querySelector('[data-google-error]');

    if (!error) {
        return;
    }

    error.textContent = message;
    error.classList.remove('hidden');
}

async function initGoogleButtons() {
    const containers = Array.from(document.querySelectorAll('[data-google-button]'));

    if (containers.length === 0) {
        return;
    }

    try {
        await loadGoogleScript();
    } catch (error) {
        containers.forEach((container) => {
            showGoogleError(container, 'Não foi possível carregar o login do Google. Recarregue a página.');
        });

        return;
    }

    const clientId = containers[0].dataset.clientId;

    if (!clientId || !window.google?.accounts?.id) {
        containers.forEach((container) => {
            showGoogleError(container, 'O login com Google não está configurado.');
        });

        return;
    }

    window.google.accounts.id.initialize({
        client_id: clientId,
        callback: submitGoogleCredential,
        ux_mode: 'popup',
        auto_select: false,
        cancel_on_tap_outside: true,
        itp_support: true,
    });

    containers.forEach((container) => {
        window.google.accounts.id.renderButton(container, {
            type: 'standard',
            theme: 'filled_black',
            size: 'large',
            text: container.dataset.text || 'signin_with',
            shape: 'rectangular',
            logo_alignment: 'left',
            width: Number(container.dataset.width || 320),
            locale: 'pt-BR',
        });
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initGoogleButtons);
} else {
    initGoogleButtons();
}
