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
        overlay.classList.add('active');
        // remove x-clock
        overlay.removeAttribute('x-cloak');
        document.body.style.overflow = 'hidden';
        
        if (!searchStarted) {
            try {
                //console.log('Starting InstantSearch...');
                search.start();
                searchStarted = true;
                //console.log('InstantSearch started successfully');
            } catch (error) {
                console.error('Error starting InstantSearch:', error);
            }
        }
    };

    const triggerEvents = ['focus',' click'];
    triggerEvents.forEach(event => {
        const target = event === 'focus' || event === 'click' ? searchInput : document;
        target.addEventListener(event, loadScripts, { once: true });
    });
})();