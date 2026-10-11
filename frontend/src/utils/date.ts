const pad = (n: number) => String(n).padStart(2, '0')

/** dd-mm-yyyy, in the viewer's local time. Empty for null. */
export function shortDate(iso: string | null | undefined): string {
  if (!iso) return ''
  const d = new Date(iso)
  return `${pad(d.getDate())}-${pad(d.getMonth() + 1)}-${d.getFullYear()}`
}

/** dd-mm-yyyy HH:mm (24-hour). */
export function shortDateTime(iso: string | null | undefined): string {
  if (!iso) return ''
  const d = new Date(iso)
  return `${shortDate(iso)} ${pad(d.getHours())}:${pad(d.getMinutes())}`
}
