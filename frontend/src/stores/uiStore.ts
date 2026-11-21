import { create } from 'zustand'

interface UIState {
  sidebarCollapsed: boolean
  toggleSidebar: () => void
  commandPaletteOpen: boolean
  setCommandPaletteOpen: (open: boolean) => void
  activeBranchId: number | null
  setActiveBranchId: (id: number | null) => void
  theme: 'light' | 'dark'
  toggleTheme: () => void
}

export const useUIStore = create<UIState>((set) => ({
  sidebarCollapsed: false,
  toggleSidebar: () => set((state) => ({ sidebarCollapsed: !state.sidebarCollapsed })),
  commandPaletteOpen: false,
  setCommandPaletteOpen: (open) => set({ commandPaletteOpen: open }),
  activeBranchId: null,
  setActiveBranchId: (id) => set({ activeBranchId: id }),
  theme: 'light',
  toggleTheme: () =>
    set((state) => {
      const next = state.theme === 'light' ? 'dark' : 'light'
      if (next === 'dark') {
        document.documentElement.classList.add('dark')
      } else {
        document.documentElement.classList.remove('dark')
      }
      return { theme: next }
    }),
}))
