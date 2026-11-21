/**
 * Converts English digits to Persian digits.
 */
export function toPersianDigits(input: string | number | null | undefined): string {
  if (input === null || input === undefined) return ''
  const str = String(input)
  const persianDigits = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹']
  return str.replace(/[0-9]/g, (w) => persianDigits[+w])
}

/**
 * Converts Persian digits to English digits.
 */
export function toEnglishDigits(input: string | null | undefined): string {
  if (!input) return ''
  const persianDigits = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹']
  let str = input
  for (let i = 0; i < 10; i++) {
    str = str.replace(new RegExp(persianDigits[i], 'g'), String(i))
  }
  return str
}
