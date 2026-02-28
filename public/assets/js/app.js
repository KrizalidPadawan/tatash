(async () => {
  const target = document.getElementById('health-status');
  if (!target) return;

  try {
    const response = await fetch('/api/v1/health', { headers: { 'Accept': 'application/json' } });
    const payload = await response.json();
    const status = payload?.data?.status || 'unknown';
    const dbOk = payload?.data?.checks?.database?.ok ? 'OK' : 'FAIL';
    const cacheOk = payload?.data?.checks?.cache?.ok ? 'OK' : 'FAIL';

    target.innerHTML = [
      '<strong>Estado:</strong> ' + status,
      '<strong>DB:</strong> ' + dbOk,
      '<strong>Cache:</strong> ' + cacheOk,
      '<strong>API:</strong> /api/v1/*'
    ].join('<br>');
  } catch (_error) {
    target.textContent = 'No fue posible consultar /api/v1/health';
  }
})();
