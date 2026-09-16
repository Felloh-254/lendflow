const formatter = new Intl.NumberFormat('en-KE', {
  style: 'currency',
  currency: 'KES',
  minimumFractionDigits: 2,
  maximumFractionDigits: 2,
})

export function formatMoney(amount) {
  if (amount === null || amount === undefined) return '—'
  return formatter.format(Number(amount))
}

export function formatDate(value) {
  if (!value) return '—'
  return new Date(value).toLocaleDateString('en-KE', { year: 'numeric', month: 'short', day: 'numeric' })
}

export function formatDateTime(value) {
  if (!value) return '—'
  return new Date(value).toLocaleString('en-KE', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })
}
