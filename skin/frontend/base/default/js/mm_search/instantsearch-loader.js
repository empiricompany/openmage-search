(function() {
    const searchInput = document.getElementById('search');
    if (!searchInput) return;

    const executeScript = (script) => {
        return new Promise((resolve) => {
            const newScript = document.createElement('script');
            newScript.type = 'text/javascript';
            const parent = script.src ? document.head : document.body;
            if (script.src) {
                newScript.src = script.src;
                newScript.onload = resolve;
                newScript.onerror = () => { console.error(`Failed to load script: ${script.src}`); resolve(); };
            } else {
                newScript.textContent = script.textContent;
            }
            parent.appendChild(newScript);
            script.remove();
            if (!script.src) {
                resolve();
            }
        });
    };

    const loadScripts = async () => {
        const lazyScripts = document.querySelectorAll('script[type="text/mm-instantsearch-lazy"]');
        for (const script of lazyScripts) {
            await executeScript(script);
        }
    };

    const triggerEvents = ['focus', 'scroll', 'mousemove', 'touchstart'];
    triggerEvents.forEach(event => {
        const target = event === 'focus' ? searchInput : window;
        target.addEventListener(event, loadScripts, { once: true });
    });
})();