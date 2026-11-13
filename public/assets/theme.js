// Applies the remembered light/dark choice before the page is painted (loaded synchronously in <head>).
try { var t = localStorage.getItem('rk-theme'); if (t) document.documentElement.dataset.theme = t; } catch (e) { /* storage blocked */ }
