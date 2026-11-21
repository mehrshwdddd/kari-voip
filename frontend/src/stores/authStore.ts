import { create } from 'zustand'
import type { User } from '../types'

interface AuthState {
  user: User | null
  token: string | null
  isAuthenticated: boolean
  setAuth: (user: User, token: string) => void
  updateUser: (user: Partial<User>) => void
  logout: () => void
  hasPermission: (permission: string) => boolean
  hasRole: (role: string) => boolean
}

const STORAGE_KEY = 'voicereach_auth'

function getInitialState() {
  try {
    const raw = localStorage.getItem(STORAGE_KEY)
    if (raw) {
      const parsed = JSON.parse(raw)
      return {
        user: parsed.user || null,
        token: parsed.token || null,
        isAuthenticated: !!parsed.token,
      }
    }
  } catch {
    // Ignore JSON parse errors
  }
  return { user: null, token: null, isAuthenticated: false }
}

export const useAuthStore = create<AuthState>((set, get) => ({
  ...getInitialState(),

  setAuth: (user: User, token: string) => {
    localStorage.setItem(STORAGE_KEY, JSON.stringify({ user, token }))
    set({ user, token, isAuthenticated: true })
  },

  updateUser: (partialUser: Partial<User>) => {
    const current = get().user
    if (!current) return
    const updated = { ...current, ...partialUser }
    const token = get().token
    localStorage.setItem(STORAGE_KEY, JSON.stringify({ user: updated, token }))
    set({ user: updated })
  },

  logout: () => {
    localStorage.removeItem(STORAGE_KEY)
    set({ user: null, token: null, isAuthenticated: false })
  },

  hasPermission: (permission: string) => {
    const user = get().user
    if (!user) return false
    if (user.roles?.includes('owner')) return true
    return user.permissions?.includes(permission) ?? false
  },

  hasRole: (role: string) => {
    const user = get().user
    if (!user) return false
    return user.roles?.includes(role) ?? false
  },
}))
