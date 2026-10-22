const API_BASE = import.meta.env.VITE_API_BASE || '/api'

export async function submitAuth(path, payload) {
  const response = await fetch(`${API_BASE}/${path}`, {
    method: 'POST',
    credentials: 'include',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload),
  })
  const data = await response.json().catch(() => ({}))
  if (!response.ok || !['success', 'warning'].includes(data.status)) {
    throw new Error(data.message || 'Không thể kết nối máy chủ. Vui lòng thử lại.')
  }
  return data
}

export function getAppUrl(path) {
  const configuredUrl = import.meta.env.VITE_APP_URL
  const appOrigin = configuredUrl
    ? new URL(configuredUrl).origin
    : (typeof window === 'undefined' ? 'http://localhost:8080' : window.location.origin)
  const appBase = new URL(appOrigin)
  if (!configuredUrl && appBase.port === '5173') appBase.port = '8080'
  return new URL(path.replace(/^\/+/, ''), `${appBase.origin}/`).toString()
}
