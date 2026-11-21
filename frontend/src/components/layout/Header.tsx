import React from 'react'
import { Search, Moon, Sun, User as UserIcon, LogOut, Menu } from 'lucide-react'
import { useAuthStore } from '../../stores/authStore'
import { useUIStore } from '../../stores/uiStore'
import { fa } from '../../i18n/fa'

export const Header: React.FC = () => {
  const { user, logout } = useAuthStore()
  const { toggleSidebar, theme, toggleTheme, setCommandPaletteOpen } = useUIStore()

  return (
    <header className="sticky top-0 z-30 flex h-16 w-full items-center justify-between border-b border-slate-200/80 bg-white/80 px-4 backdrop-blur-md dark:border-slate-800 dark:bg-slate-900/80">
      {/* Right side: Sidebar toggle button (mobile) + Brand/Branch */}
      <div className="flex items-center gap-3">
        <button
          type="button"
          onClick={toggleSidebar}
          className="flex h-10 w-10 items-center justify-center rounded-xl text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800 md:hidden"
        >
          <Menu className="h-5 w-5" />
        </button>

        <div className="flex flex-col">
          <div className="flex items-center gap-2">
            <span className="font-extrabold text-sm text-slate-900 dark:text-slate-100">
              {fa.appName}
            </span>
            {user?.branch && (
              <span className="rounded-md bg-blue-50 px-2 py-0.5 text-[11px] font-medium text-blue-700 dark:bg-blue-950/60 dark:text-blue-300">
                {user.branch.name}
              </span>
            )}
          </div>
          <span className="text-[11px] text-slate-400 hidden sm:block">
            {fa.appSubtitle}
          </span>
        </div>
      </div>

      {/* Center: Command Palette Trigger Button */}
      <button
        type="button"
        onClick={() => setCommandPaletteOpen(true)}
        className="hidden sm:flex items-center gap-3 px-3.5 py-1.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50/80 dark:bg-slate-800/40 text-slate-400 hover:border-slate-300 dark:hover:border-slate-700 transition-colors w-72 justify-between"
      >
        <div className="flex items-center gap-2 text-xs">
          <Search className="h-4 w-4" />
          <span>{fa.common.commandPalette}</span>
        </div>
        <kbd className="px-1.5 py-0.5 text-[10px] font-mono bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-md text-slate-500">
          Ctrl K
        </kbd>
      </button>

      {/* Left side: Theme toggle + User Profile info */}
      <div className="flex items-center gap-2">
        <button
          type="button"
          onClick={toggleTheme}
          title="تغییر حالت شب و روز"
          className="flex h-10 w-10 items-center justify-center rounded-xl text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800 transition-colors"
        >
          {theme === 'dark' ? <Sun className="h-5 w-5 text-amber-400" /> : <Moon className="h-5 w-5" />}
        </button>

        <div className="flex items-center gap-2.5 pr-2 mr-2 border-r border-slate-200 dark:border-slate-800">
          <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-blue-600 text-white font-bold text-xs shadow-xs">
            {user?.name ? user.name[0] : <UserIcon className="h-4 w-4" />}
          </div>
          <div className="hidden lg:flex flex-col text-right">
            <span className="text-xs font-bold text-slate-800 dark:text-slate-200">
              {user?.name || 'کاربر سیستم'}
            </span>
            <span className="text-[10px] text-slate-400">
              {user?.roles?.[0] || 'اپراتور'}
            </span>
          </div>
          <button
            type="button"
            onClick={logout}
            title={fa.nav.logout}
            className="flex h-9 w-9 items-center justify-center rounded-xl text-slate-400 hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-950/40 transition-colors"
          >
            <LogOut className="h-4 w-4" />
          </button>
        </div>
      </div>
    </header>
  )
}
