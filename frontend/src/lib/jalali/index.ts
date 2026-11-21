import { toPersianDigits } from '../numerals'

const PERSIAN_MONTHS = [
  'فروردین', 'اردیبهشت', 'خرداد',
  'تیر', 'مرداد', 'شهریور',
  'مهر', 'آبان', 'آذر',
  'دی', 'بهمن', 'اسفند'
]

// Gregorian to Jalali conversion algorithm
export function gregorianToJalali(gy: number, gm: number, gd: number): [number, number, number] {
  const g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334]
  let jy = (gy <= 1600) ? 0 : 979
  gy -= (gy <= 1600) ? 621 : 1600
  const gy2 = (gm > 2) ? (gy + 1) : gy
  let days = (365 * gy) + Math.floor((gy2 + 3) / 4) - Math.floor((gy2 + 99) / 100) +
    Math.floor((gy2 + 399) / 400) - 80 + gd + g_d_m[gm - 1]
  jy += 33 * Math.floor(days / 12053)
  days %= 12053
  jy += 4 * Math.floor(days / 1461)
  days %= 1461
  if (days > 365) {
    jy += Math.floor((days - 1) / 365)
    days = (days - 1) % 365
  }
  const jm = (days < 186) ? 1 + Math.floor(days / 31) : 7 + Math.floor((days - 186) / 30)
  const jd = 1 + ((days < 186) ? (days % 31) : ((days - 186) % 30))
  return [jy, jm, jd]
}

/**
 * Format ISO or Date string into Jalali format: ۱۴۰۵/۰۸/۲۴ or ۱۴ آبان ۱۴۰۵
 */
export function formatJalali(dateInput: string | Date | null | undefined, includeTime = false): string {
  if (!dateInput) return '-'
  const date = typeof dateInput === 'string' ? new Date(dateInput) : dateInput
  if (isNaN(date.getTime())) return '-'

  const [jy, jm, jd] = gregorianToJalali(date.getFullYear(), date.getMonth() + 1, date.getDate())
  const monthName = PERSIAN_MONTHS[jm - 1]

  const formattedDate = `${toPersianDigits(jd)} ${monthName} ${toPersianDigits(jy)}`

  if (includeTime) {
    const hours = String(date.getHours()).padStart(2, '0')
    const minutes = String(date.getMinutes()).padStart(2, '0')
    return `${formattedDate} - ${toPersianDigits(`${hours}:${minutes}`)}`
  }

  return formattedDate
}

/**
 * Format Jalali numeric date: ۱۴۰۵/۰۸/۲۴
 */
export function formatJalaliNumeric(dateInput: string | Date | null | undefined): string {
  if (!dateInput) return '-'
  const date = typeof dateInput === 'string' ? new Date(dateInput) : dateInput
  if (isNaN(date.getTime())) return '-'

  const [jy, jm, jd] = gregorianToJalali(date.getFullYear(), date.getMonth() + 1, date.getDate())
  const pad = (n: number) => String(n).padStart(2, '0')
  return toPersianDigits(`${jy}/${pad(jm)}/${pad(jd)}`)
}
