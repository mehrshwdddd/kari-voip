import React from 'react'
import { NavLink } from 'react-router-dom'
import {
  LayoutDashboard,
  Headphones,
  PhoneCall,
  Megaphone,
  Calendar,
  Receipt,
  MessageSquare,
  Bot,
  BarChart3,
  Users,
  Building,
  ShieldCheck,
  Settings,
  Code2,
  ChevronLeft,
  ChevronRight,
} from 'lucide-react'
import { useUIStore } from '../../stores/uiStore'
import { useAuthStore } from '../../stores/authStore'
import { fa } from '../../i18n/fa'
import { cn } from '../../lib/utils'

export const Sidebar: React.FC = () => {
  const { sidebarCollapsed, toggleSidebar } = useUIStore()
  const { hasRole } = useAuthStore()

  const navItems = [
    {
      label: fa.nav.dashboard,
      path: '/dashboard',
      icon: <LayoutDashboard className="h-5 w-5" />,
    },
    {
      label: fa.nav.workspace,
      path: '/workspace',
      icon: <Headphones className="h-5 w-5" />,
    },
    {
      label: fa.nav.calls,
      path: '/calls',
      icon: <PhoneCall className="h-5 w-5" />,
    },
    {
      label: fa.nav.campaigns,
      path: '/campaigns',
      icon: <Megaphone className="h-5 w-5" />,
    },
    {
      label: fa.nav.contacts,
      path: '/contacts',
      icon: <Users className="h-5 w-5" />,
    },
    {
      label: fa.nav.appointments,
      path: '/appointments',
      icon: <Calendar className="h-5 w-5" />,
    },
    {
      label: fa.nav.invoices,
      path: '/invoices',
      icon: <Receipt className="h-5 w-5" />,
    },
    {
      label: fa.nav.messaging,
      path: '/messaging',
      icon: <MessageSquare className="h-5 w-5" />,
    },
    {
      label: fa.nav.aiReceptionist,
      path: '/ai-receptionist',
      icon: <Bot className="h-5 w-5" />,
    },
    {
      label: fa.nav.reports,
      path: '/reports',
      icon: <BarChart3 className="h-5 w-5" />,
    },
    {
      label: fa.nav.branches,
      path: '/branches',
      icon: <Building className="h-5 w-5" />,
    },
    {
      label: fa.nav.users,
      path: '/users',
      icon: <ShieldCheck className="h-5 w-5" />,
      adminOnly: true,
    },
    {
      label: fa.nav.settings,
      path: '/settings',
      icon: <Settings className="h-5 w-5" />,
      adminOnly: true,
    },
    {
      label: fa.nav.developer,
      path: '/developer',
      icon: <Code2 className="h-5 w-5" />,
      adminOnly: true,
    },
  ]

  return (
    <aside
      className={cn(
        'sticky top-0 z-40 hidden md:flex flex-col h-screen border-l border-slate-200/80 bg-white dark:border-slate-800 dark:bg-slate-900 transition-all duration-200',
        sidebarCollapsed ? 'w-20' : 'w-64'
      )}
    >
      {/* Brand Header */}
      <div className="flex h-16 items-center justify-between px-4 border-b border-slate-100 dark:border-slate-800">
        <div className="flex items-center gap-3 overflow-hidden">
          <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-tr from-blue-700 to-blue-500 text-white shadow-md shadow-blue-500/20">
            <PhoneCall className="h-5 w-5" />
          </div>
          {!sidebarCollapsed && (
            <div className="flex flex-col">
              <span className="font-black text-sm tracking-tight text-slate-900 dark:text-slate-100">
                {fa.appName}
              </span>
              <span className="text-[10px] text-blue-600 dark:text-blue-400 font-bold">
                نسخه ۲.۰
              </span>
            </div>
          )}
        </div>
      </div>

      {/* Nav items */}
      <nav className="flex-1 space-y-1.5 p-3 overflow-y-auto">
        {navItems.map((item) => {
          if (item.adminOnly && !hasRole('owner')) return null

          return (
            <NavLink
              key={item.path}
              to={item.path}
              title={sidebarCollapsed ? item.label : undefined}
              className={({ isActive }) =>
                cn(
                  'flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-semibold transition-all duration-150',
                  isActive
                    ? 'bg-blue-600 text-white shadow-sm shadow-blue-500/20'
                    : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800/60'
                )
              }
            >
              <div className="shrink-0">{item.icon}</div>
              {!sidebarCollapsed && <span className="truncate">{item.label}</span>}
            </NavLink>
          )
        })}
      </nav>

      {/* Collapse Toggle Footer */}
      <div className="p-3 border-t border-slate-100 dark:border-slate-800">
        <button
          type="button"
          onClick={toggleSidebar}
          className="w-full flex items-center justify-center gap-2 p-2.5 rounded-xl text-slate-400 hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-800 transition-colors cursor-pointer text-xs"
        >
          {sidebarCollapsed ? (
            <ChevronLeft className="h-4 w-4" />
          ) : (
            <>
              <ChevronRight className="h-4 w-4" />
              <span>جمع کردن منو</span>
            </>
          )}
        </button>
      </div>
    </aside>
  )
}
