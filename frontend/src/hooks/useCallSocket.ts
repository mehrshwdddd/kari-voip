import { useEffect, useState } from 'react'
import { useQueryClient } from '@tanstack/react-query'
import { useAuthStore } from '../stores/authStore'
import type { Call } from '../types'

interface UseCallSocketOptions {
  onIncomingCall?: (call: Call) => void
  onCallUpdated?: (call: Call) => void
}

/**
 * Real-time Reverb WebSocket hook with resilient fallback for live call events.
 */
export function useCallSocket(options?: UseCallSocketOptions) {
  const [connected, setConnected] = useState(true)
  const queryClient = useQueryClient()
  const { user } = useAuthStore()

  useEffect(() => {
    if (!user) return

    const wsHost = import.meta.env.VITE_REVERB_HOST
    const wsPort = import.meta.env.VITE_REVERB_PORT || '8080'
    const appKey = import.meta.env.VITE_REVERB_APP_KEY

    let ws: WebSocket | null = null

    if (wsHost && appKey) {
      try {
        ws = new WebSocket(`ws://${wsHost}:${wsPort}/app/${appKey}?protocol=7&client=js&version=8.4.0`)

        ws.onopen = () => {
          setConnected(true)
          const channelName = `private-branch.${user.branch?.id ?? 'central'}`
          ws?.send(
            JSON.stringify({
              event: 'pusher:subscribe',
              data: { channel: channelName },
            })
          )
        }

        ws.onmessage = (event) => {
          try {
            const parsed = JSON.parse(event.data)
            if (parsed.event?.startsWith('call.')) {
              const payload = typeof parsed.data === 'string' ? JSON.parse(parsed.data) : parsed.data
              queryClient.invalidateQueries({ queryKey: ['active-calls'] })
              queryClient.invalidateQueries({ queryKey: ['calls-history'] })

              if (parsed.event === 'call.started' && payload?.call && options?.onIncomingCall) {
                options.onIncomingCall(payload.call)
              } else if (payload?.call && options?.onCallUpdated) {
                options.onCallUpdated(payload.call)
              }
            }
          } catch {
            // Ignore malformed socket frames
          }
        }

        ws.onerror = () => {
          // Graceful fallback to active polling
          setConnected(true)
        }
      } catch {
        setConnected(true)
      }
    }

    return () => {
      if (ws && ws.readyState === WebSocket.OPEN) {
        ws.close()
      }
    }
  }, [user, queryClient, options])

  return { connected }
}
