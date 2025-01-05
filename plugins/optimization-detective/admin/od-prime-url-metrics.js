(function(){
  document.addEventListener('DOMContentLoaded', () => {
    sessionStorage.removeItem('odStorageLockTime');
    // Make sure you have <div id="od-prime-app"></div> in your HTML/PHP.
    const container = document.getElementById('od-prime-app');
    if (!container || !odPrimeData) {
      return;
    }

    const urls = Array.isArray(odPrimeData.urls) ? odPrimeData.urls : [];
    let breakpoints = Array.isArray(odPrimeData.breakpoints) ? odPrimeData.breakpoints : [];

    // Indices
    let currentUrlIndex = 0;
    let currentBreakpointIndex = 0;

    // Create Buttons
    const btnStart = document.createElement('button');
    btnStart.textContent = 'Start';

    const btnLoad = document.createElement('button');
    btnLoad.textContent = 'Load This Breakpoint';
    btnLoad.disabled = true;

    const btnNextBreakpoint = document.createElement('button');
    btnNextBreakpoint.textContent = 'Next Breakpoint';
    btnNextBreakpoint.disabled = true;

    const btnNextUrl = document.createElement('button');
    btnNextUrl.textContent = 'Next URL';
    btnNextUrl.disabled = true;

    const info = document.createElement('div');
    info.style.marginTop = '10px';
    info.innerText = 'Click "Start" to begin.';

    const iframe = document.createElement('iframe');
    iframe.style.display = 'block';
    iframe.style.marginTop = '20px';
    iframe.width = '900';
    iframe.height = '600';
    iframe.style.border = '1px solid #ccc';

    // Layout rows
    const row1 = document.createElement('div');
    row1.style.marginBottom = '10px';
    row1.appendChild(btnStart);

    const row2 = document.createElement('div');
    row2.style.marginBottom = '10px';
    row2.appendChild(btnLoad);
    row2.appendChild(btnNextBreakpoint);

    const row3 = document.createElement('div');
    row3.style.marginBottom = '10px';
    row3.appendChild(btnNextUrl);

    container.appendChild(row1);
    container.appendChild(row2);
    container.appendChild(row3);
    container.appendChild(info);
    container.appendChild(iframe);

    // Helpers
    function getCurrentUrl() {
      return urls[currentUrlIndex];
    }
    function getCurrentBreakpoint() {
      return breakpoints[currentBreakpointIndex];
    }

    function updateInfoDisplay(msg) {
      const totalUrls = urls.length;
      const totalBps = breakpoints.length;

      const urlStr = `URL ${currentUrlIndex + 1} / ${totalUrls}`;
      const bpStr = `Breakpoint ${currentBreakpointIndex + 1} / ${totalBps}`;

      let detailStr = '';
      if (currentUrlIndex < totalUrls && currentBreakpointIndex < totalBps) {
        const url = getCurrentUrl();
        const bp = getCurrentBreakpoint();
        detailStr = `Current: URL=${url} | ${bp.width}x${bp.height}`;
      }

      info.innerHTML = `
        [${urlStr}]<br/>
        [${bpStr}]<br/>
        [${detailStr}]<br/>
        ${msg || ''}
      `;
    }

    function loadCurrentIframe() {
      if (currentUrlIndex >= urls.length || currentBreakpointIndex >= breakpoints.length) {
        updateInfoDisplay('All done or out of range');
        return;
      }
      const url = getCurrentUrl();
      const bp = getCurrentBreakpoint();

      const paramChar = url.includes('?') ? '&' : '?';
      const loadUrl = `${url}${paramChar}od_prime=1`;

      iframe.width = String(bp.width);
      iframe.height = String(bp.height);

      updateInfoDisplay(`Loading URL at breakpoint width=${bp.width}, height=${bp.height}, aspect=${bp.ar}}`);
      iframe.src = loadUrl;
    }

    btnStart.addEventListener('click', () => {
      currentUrlIndex = 0;
      currentBreakpointIndex = 0;
      btnLoad.disabled = false;
      btnNextBreakpoint.disabled = false;
      btnNextUrl.disabled = false;

      updateInfoDisplay('Ready. Click "Load This Breakpoint" to load the first URL/breakpoint.');
    });

    btnLoad.addEventListener('click', () => {
      sessionStorage.removeItem('odStorageLockTime');
      loadCurrentIframe();
    });

    btnNextBreakpoint.addEventListener('click', () => {
      currentBreakpointIndex++;
      if (currentBreakpointIndex >= breakpoints.length) {
        currentBreakpointIndex = breakpoints.length - 1;
        updateInfoDisplay('No more breakpoints. Maybe go to Next URL.');
      } else {
        updateInfoDisplay('Now at next breakpoint. Click "Load This Breakpoint".');
      }
    });

    btnNextUrl.addEventListener('click', () => {
      currentUrlIndex++;
      currentBreakpointIndex = 0;
      if (currentUrlIndex >= urls.length) {
        currentUrlIndex = urls.length - 1;
        updateInfoDisplay('No more URLs left.');
      } else {
        updateInfoDisplay('Now at next URL, first breakpoint. Click "Load This Breakpoint".');
      }
    });

    window.addEventListener('message', (event) => {
      if ('done_prime' === event.data ) {
        info.innerHTML += ' … iframe loaded successfully.';
      } else if ('failed_prime' === event.data) {
        info.innerHTML += ' … iframe failed to load.';
      }
    });
  });
})();
