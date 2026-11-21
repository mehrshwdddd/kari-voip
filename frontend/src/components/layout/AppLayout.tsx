import React from 'react'
import { Outlet, Navigate } from 'react-router-dom'
import { Header } from './Header'
import { Sidebar } from './Sidebar'
import { MobileBottomDock } from './MobileBottomDock'
import { CommandPalette } from '../devigner/CommandPalette'
import { useAuthStore } from '../../stores/authStore'

export const AppLayout: React.FC = () => {
  const { isAuthenticated } = useAuthStore()

  if (!isAuthenticated) {
    return <Navigate to="/login" replace />
  }

  return (
    <div className="flex min-h-screen bg-slate-50 dark:bg-slate-950 font-sans text-slate-900 dark:text-slate-100 antialiased selection:bg-blue-600 selection:text-white">
      {/* Sidebar for desktop */}
      <Sidebar />

      {/* Main Content Area */}
      <div className="flex-1 flex flex-col min-w-0">
        <Header />

        <main className="flex-1 p-4 md:p-6 lg:p-8 max-w-7xl w-full mx-auto pb-24 md:pb-8">
          <Outlet />
        </main>

        {/* 56px Mobile Bottom Dock */}
        <MobileBottomDock />

        {/* Global Command Palette */}
        <CommandPalette />
      </div>
    </div>
  )
}
