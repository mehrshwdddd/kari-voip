import React, { useState, useEffect } from 'react'
import { motion, AnimatePresence } from 'framer-motion'
import { Search, User, Phone, Building, Settings, X, ArrowLeft, Code2 } from 'lucide-react'
import { useNavigate } from 'react-router-dom'
import { useUIStore } from '../../stores/uiStore'

interface ActionItem {
  id: string
  title: string
  subtitle: string
  icon: React.ReactNode
  path: string
}

export const CommandPalette: React.FC = () => {
  const { commandPaletteOpen, setCommandPaletteOpen } = useUIStore()
  const [query, setQuery] = useState('')
  const navigate = useNavigate()

  useEffect(() => {
    const handleKeyDown = (e: KeyboardEvent) => {
      if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
        e.preventDefault()
        setCommandPaletteOpen(!commandPaletteOpen)
      }
      if (e.key === 'Escape' && commandPaletteOpen) {
        setCommandPaletteOpen(false)
      }
    }
    window.addEventListener('keydown', handleKeyDown)
    return () => window.removeEventListener('keydown', handleKeyDown)
  }, [commandPaletteOpen, setCommandPaletteOpen])

  const defaultActions: ActionItem[] = [
    {
      id: 'contacts',
      title: 'بانک اطلاعات مخاطبین',
      subtitle: 'مدیریت و مشاهده پرونده کامل مشتریان (Customer 360)',
      icon: <User className="h-4 w-4 text-blue-500" />,
      path: '/contacts',
    },
    {
      id: 'workspace',
      title: 'میز کار منشی و تلفن زنده (Screen Pop)',
      subtitle: 'پاسخگویی به تماس‌ها، کنترل مکالمه و پاپ‌آپ پرونده تماس‌گیرنده',
      icon: <Phone className="h-4 w-4 text-emerald-500" />,
      path: '/workspace',
    },
    {
      id: 'calls',
      title: 'تاریخچه و گزارش تماس‌ها',
      subtitle: 'مشاهده لاگ تماس‌های ورودی و خروجی و پخش ایمن رکوردینگ‌ها',
      icon: <Phone className="h-4 w-4 text-blue-500" />,
      path: '/calls',
    },
    {
      id: 'campaigns',
      title: 'موتور کمپین‌های صوتی و لیست سیاه DNC',
      subtitle: 'اجرای خودکار تماس‌های صوتی انبوه، گزارش‌های Recharts و مدیریت DNC',
      icon: <Phone className="h-4 w-4 text-amber-500" />,
      path: '/campaigns',
    },
    {
      id: 'appointments',
      title: 'تقویم و موتور نوبت‌دهی هوشمند',
      subtitle: 'رزرو نوبت، بررسی خودکار تداخل زمانی و ارسال پیامک یادآور',
      icon: <User className="h-4 w-4 text-indigo-500" />,
      path: '/appointments',
    },
    {
      id: 'invoices',
      title: 'صورت‌حساب، چاپگر رسید (dqnamo) و دفتر روزنامه',
      subtitle: 'صدور فاکتور رسمی، چاپ رسید حرارتی، ثبت پرداخت و کیف پول مشتری',
      icon: <Building className="h-4 w-4 text-emerald-500" />,
      path: '/invoices',
    },
    {
      id: 'messaging',
      title: 'اتوماسیون پیامک (کاوه‌نگار و قاصدک)',
      subtitle: 'ارسال پیامک، مدیریت قالب‌های متغیرمحور و مشاهده لاگ پیام‌ها',
      icon: <Settings className="h-4 w-4 text-blue-500" />,
      path: '/messaging',
    },
    {
      id: 'ai-receptionist',
      title: 'منشی هوشمند (AI IVR) و سازنده بصری جریان تماس',
      subtitle: 'تشخیص گفتار فارسی (STT)، سنتز صدا (TTS)، شبیه‌ساز زنده و گراف جریان',
      icon: <Phone className="h-4 w-4 text-indigo-500" />,
      path: '/ai-receptionist',
    },
    {
      id: 'reports',
      title: 'گزارش‌ها و آنالیتیکس مدیریتی (Recharts & Excel)',
      subtitle: 'نمودارهای تماس، درآمد، کمپین، عملکرد اپراتورها و خروجی اکسل',
      icon: <Building className="h-4 w-4 text-emerald-500" />,
      path: '/reports',
    },
    {
      id: 'branches',
      title: 'مدیریت شعب',
      subtitle: 'تنظیمات مراکز و ساعت‌های کاری',
      icon: <Building className="h-4 w-4 text-amber-500" />,
      path: '/branches',
    },
    {
      id: 'settings',
      title: 'تنظیمات سیستم',
      subtitle: 'پیکربندی خطوط VoIP، پنل پیامک و اطلاعات شرکت',
      icon: <Settings className="h-4 w-4 text-slate-500" />,
      path: '/settings',
    },
    {
      id: 'developer',
      title: 'توسعه‌دهندگان و API',
      subtitle: 'مدیریت کلیدهای دسترسی API، وب‌هوک‌های رویدادمحور و لاگ‌های ممیزی',
      icon: <Code2 className="h-4 w-4 text-emerald-500" />,
      path: '/developer',
    },
  ]

  const filtered = defaultActions.filter(
    (item) =>
      item.title.toLowerCase().includes(query.toLowerCase()) ||
      item.subtitle.toLowerCase().includes(query.toLowerCase())
  )

  const handleSelect = (path: string) => {
    setCommandPaletteOpen(false)
    setQuery('')
    navigate(path)
  }

  return (
    <AnimatePresence>
      {commandPaletteOpen && (
        <div className="fixed inset-0 z-50 flex items-start justify-center pt-20 px-4">
          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            className="fixed inset-0 bg-slate-950/60 backdrop-blur-xs"
            onClick={() => setCommandPaletteOpen(false)}
          />

          <motion.div
            initial={{ opacity: 0, scale: 0.95, y: -20 }}
            animate={{ opacity: 1, scale: 1, y: 0 }}
            exit={{ opacity: 0, scale: 0.95, y: -20 }}
            transition={{ type: 'spring', duration: 0.25 }}
            className="relative w-full max-w-xl rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-2xl overflow-hidden"
          >
            {/* Search Input Bar */}
            <div className="flex items-center px-4 py-3.5 border-b border-slate-100 dark:border-slate-800">
              <Search className="h-5 w-5 text-slate-400 ml-3 shrink-0" />
              <input
                autoFocus
                type="text"
                placeholder="جستجو در بخش‌ها، دستورات یا مشتریان..."
                value={query}
                onChange={(e) => setQuery(e.target.value)}
                className="w-full bg-transparent text-sm text-slate-800 dark:text-slate-100 placeholder:text-slate-400 focus:outline-none"
              />
              <button
                type="button"
                onClick={() => setCommandPaletteOpen(false)}
                className="p-1 rounded-lg text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
              >
                <X className="h-4 w-4" />
              </button>
            </div>

            {/* Results List */}
            <div className="max-h-80 overflow-y-auto p-2 space-y-1">
              {filtered.length === 0 ? (
                <div className="py-8 text-center text-xs text-slate-400">
                  موردی با این عبارت یافت نشد.
                </div>
              ) : (
                filtered.map((item) => (
                  <button
                    key={item.id}
                    onClick={() => handleSelect(item.path)}
                    className="w-full flex items-center justify-between p-3 rounded-xl hover:bg-slate-50 dark:hover:bg-slate-800/60 transition-colors group text-right cursor-pointer"
                  >
                    <div className="flex items-center gap-3">
                      <div className="p-2 rounded-lg bg-slate-100 dark:bg-slate-800 group-hover:scale-110 transition-transform">
                        {item.icon}
                      </div>
                      <div>
                        <div className="text-xs font-bold text-slate-800 dark:text-slate-200">
                          {item.title}
                        </div>
                        <div className="text-[11px] text-slate-400 mt-0.5">
                          {item.subtitle}
                        </div>
                      </div>
                    </div>
                    <ArrowLeft className="h-4 w-4 text-slate-400 opacity-0 group-hover:opacity-100 -translate-x-1 group-hover:translate-x-0 transition-all" />
                  </button>
                ))
              )}
            </div>

            {/* Footer */}
            <div className="px-4 py-2 bg-slate-50 dark:bg-slate-900/60 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between text-[11px] text-slate-400">
              <span>برای انتخاب کلیک کنید</span>
              <span>کلید خروج: Esc</span>
            </div>
          </motion.div>
        </div>
      )}
    </AnimatePresence>
  )
}
