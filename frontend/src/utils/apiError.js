export function apiErrorMessage(error) {
  const data = error?.response?.data
  if (!data) return 'Something went wrong. Please try again.'
  if (data.error?.message) return data.error.message
  if (data.message) return data.message
  if (data.errors) {
    const first = Object.values(data.errors)[0]
    if (Array.isArray(first)) return first[0]
  }
  return 'Something went wrong. Please try again.'
}
