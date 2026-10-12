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

/** Server timestamps stored as "Y-m-d H:i:s" (UTC, see App/bootstrap.php) as ISO, so they show in local time. */
export function fromServerTime(value: string | null | undefined): string | null {
  if (!value) return null
  return value.includes('T') ? value : `${value.replace(' ', 'T')}Z`
}
