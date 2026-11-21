import React, { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import {
  Search,
  PhoneIncoming,
  PhoneOutgoing,
  PhoneCall,
  PhoneMissed,
  Clock,
  Volume2,
  Headphones,
} from 'lucide-react'
import { useQuery } from '@tanstack/react-query'
import { apiClient } from '../../../lib/api/client'
import { Card } from '../../../components/ui/Card'
import { Button } from '../../../components/ui/Button'
import { Input } from '../../../components/ui/Input'
import { Badge } from '../../../components/ui/Badge'
import { Modal } from '../../../components/ui/Modal'
import { EmptyState } from '../../../components/ui/EmptyState'
import { toPersianDigits } from '../../../lib/numerals'
import { formatJalali } from '../../../lib/jalali'
import { fa } from '../../../i18n/fa'
import type { Call, PaginatedResponse } from '../../../types'

export const CallsHistoryPage: React.FC = () => {
  const navigate = useNavigate()
  const [search, setSearch] = useState('')
  const [direction, setDirection] = useState('')
  const [status, setStatus] = useState('')
  const [audioModalUrl, setAudioModalUrl] = useState<string | null>(null)
  const [audioCallInfo, setAudioCallInfo] = useState<Call | null>(null)
  const [audioError, setAudioError] = useState<string | null>(null)

  const { data, isLoading } = useQuery<PaginatedResponse<Call>>({
    queryKey: ['calls-history', search, direction, status],
    queryFn: async () => {
      const params = new URLSearchParams()
      if (search) params.append('search', search)
      if (direction) params.append('direction', direction)
      if (status) params.append('status', status)
      const res = await apiClient.get(`/calls?${params.toString()}`)
      return res.data
    },
  })

  const handlePlayRecording = async (call: Call) => {
    setAudioError(null)
    try {
      const res = await apiClient.get(`/calls/${call.id}/recording`)
      setAudioCallInfo(call)
      setAudioModalUrl(res.data.recording_url)
    } catch (err: any) {
      setAudioError(
        err.response?.data?.message ||
          'شما مجوز دسترسی به فایل‌های ضبط‌شده را ندارید (خطای 403).'
      )
    }
  }

  const calls = data?.data || []
  const stats = data?.stats

  const renderStatusBadge = (st: Call['status']) => {
    switch (st) {
      case 'ringing':
        return <Badge variant="warning">{fa.calls.statusRinging}</Badge>
      case 'active':
        return <Badge variant="success">{fa.calls.statusActive}</Badge>
      case 'on_hold':
        return <Badge variant="primary">{fa.calls.statusOnHold}</Badge>
      case 'transferred':
        return <Badge variant="primary">{fa.calls.statusTransferred}</Badge>
      case 'missed':
        return <Badge variant="danger">{fa.calls.statusMissed}</Badge>
      case 'failed':
        return <Badge variant="danger">{fa.calls.statusFailed}</Badge>
      default:
        return <Badge variant="neutral">{fa.calls.statusEnded}</Badge>
    }
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
          <h1 className="text-xl font-black text-slate-900 dark:text-slate-100">
            {fa.calls.title}
          </h1>
          <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
            {fa.calls.subtitle}
          </p>
        </div>
        <Button
          size="md"
          icon={<Headphones className="h-4 w-4" />}
          onClick={() => navigate('/workspace')}
        >
          {fa.nav.workspace}
        </Button>
      </div>

      {audioError && (
        <div className="p-3.5 rounded-2xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800 text-xs text-rose-600 dark:text-rose-400">
          {audioError}
        </div>
      )}

      {/* Summary Stats Cards */}
      <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <Card className="flex items-center justify-between p-5">
          <div>
            <span className="text-xs text-slate-500">{fa.calls.totalCallsStat}</span>
            <div className="text-2xl font-black text-slate-900 dark:text-slate-100 mt-1">
              {toPersianDigits(stats?.total_calls ?? calls.length)}
            </div>
          </div>
          <div className="flex h-11 w-11 items-center justify-center rounded-2xl bg-blue-50 dark:bg-blue-950/50 text-blue-600">
            <PhoneCall className="h-5 w-5" />
          </div>
        </Card>

        <Card className="flex items-center justify-between p-5">
          <div>
            <span className="text-xs text-slate-500">{fa.calls.missedCallsStat}</span>
            <div className="text-2xl font-black text-rose-600 mt-1">
              {toPersianDigits(stats?.missed_calls ?? 0)}
            </div>
          </div>
          <div className="flex h-11 w-11 items-center justify-center rounded-2xl bg-rose-50 dark:bg-rose-950/50 text-rose-600">
            <PhoneMissed className="h-5 w-5" />
          </div>
        </Card>

        <Card className="flex items-center justify-between p-5">
          <div>
            <span className="text-xs text-slate-500">{fa.calls.avgDurationStat}</span>
            <div className="text-2xl font-black text-slate-900 dark:text-slate-100 mt-1">
              {toPersianDigits(stats?.avg_duration ?? 0)} ثانیه
            </div>
          </div>
          <div className="flex h-11 w-11 items-center justify-center rounded-2xl bg-emerald-50 dark:bg-emerald-950/50 text-emerald-600">
            <Clock className="h-5 w-5" />
          </div>
        </Card>
      </div>

      {/* Filters */}
      <Card className="p-4">
        <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
          <Input
            placeholder={fa.calls.searchPlaceholder}
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            leadingIcon={<Search className="h-4 w-4" />}
          />

          <select
            value={direction}
            onChange={(e) => setDirection(e.target.value)}
            className="w-full min-h-[44px] rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs text-slate-900 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"
          >
            <option value="">{fa.calls.allDirections}</option>
            <option value="inbound">{fa.calls.inbound}</option>
            <option value="outbound">{fa.calls.outbound}</option>
          </select>

          <select
            value={status}
            onChange={(e) => setStatus(e.target.value)}
            className="w-full min-h-[44px] rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs text-slate-900 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"
          >
            <option value="">{fa.calls.allStatuses}</option>
            <option value="ended">{fa.calls.statusEnded}</option>
            <option value="active">{fa.calls.statusActive}</option>
            <option value="missed">{fa.calls.statusMissed}</option>
            <option value="transferred">{fa.calls.statusTransferred}</option>
          </select>
        </div>
      </Card>

      {/* Calls Table */}
      <Card className="p-0 overflow-hidden">
        {isLoading ? (
          <div className="p-12 text-center text-xs text-slate-400">
            {fa.common.loading}
          </div>
        ) : calls.length === 0 ? (
          <div className="p-8">
            <EmptyState
              title={fa.calls.noCallsFound}
              actionLabel={fa.nav.workspace}
              onAction={() => navigate('/workspace')}
            />
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-right text-xs">
              <thead className="bg-slate-50/80 dark:bg-slate-800/40 border-b border-slate-200/80 dark:border-slate-800 text-slate-500 font-bold">
                <tr>
                  <th className="px-4 py-3.5">نوع / جهت</th>
                  <th className="px-4 py-3.5">مخاطب / شماره</th>
                  <th className="px-4 py-3.5">وضعیت</th>
                  <th className="px-4 py-3.5">{fa.calls.duration}</th>
                  <th className="px-4 py-3.5">{fa.calls.agent}</th>
                  <th className="px-4 py-3.5">{fa.calls.disposition}</th>
                  <th className="px-4 py-3.5">زمان</th>
                  <th className="px-4 py-3.5 text-center">{fa.calls.recording}</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                {calls.map((call) => (
                  <tr
                    key={call.id}
                    className="hover:bg-slate-50/80 dark:hover:bg-slate-800/50 transition-colors"
                  >
                    <td className="px-4 py-3.5">
                      <div className="inline-flex items-center gap-1.5 font-bold">
                        {call.direction === 'inbound' ? (
                          <>
                            <PhoneIncoming className="h-4 w-4 text-emerald-600" />
                            <span className="text-emerald-700 dark:text-emerald-400">
                              ورودی
                            </span>
                          </>
                        ) : (
                          <>
                            <PhoneOutgoing className="h-4 w-4 text-blue-600" />
                            <span className="text-blue-700 dark:text-blue-400">
                              خروجی
                            </span>
                          </>
                        )}
                      </div>
                    </td>

                    <td className="px-4 py-3.5">
                      {call.contact ? (
                        <button
                          type="button"
                          onClick={() => navigate(`/contacts/${call.contact!.id}`)}
                          className="text-right hover:text-blue-600 transition-colors cursor-pointer"
                        >
                          <div className="font-bold text-slate-900 dark:text-slate-100">
                            {call.contact.first_name} {call.contact.last_name}
                          </div>
                          <div className="text-[11px] font-mono text-slate-400">
                            {toPersianDigits(call.caller_number)}
                          </div>
                        </button>
                      ) : (
                        <span className="font-mono text-slate-700 dark:text-slate-300">
                          {toPersianDigits(
                            call.direction === 'inbound'
                              ? call.caller_number
                              : call.callee_number
                          )}
                        </span>
                      )}
                    </td>

                    <td className="px-4 py-3.5">{renderStatusBadge(call.status)}</td>

                    <td className="px-4 py-3.5 font-mono text-slate-700 dark:text-slate-300">
                      {toPersianDigits(call.duration_seconds)} ثانیه
                    </td>

                    <td className="px-4 py-3.5 text-slate-600 dark:text-slate-300">
                      {call.user?.name || '-'}
                    </td>

                    <td className="px-4 py-3.5">
                      {call.disposition ? (
                        <span className="px-2.5 py-1 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 text-[11px] font-medium">
                          {call.disposition}
                        </span>
                      ) : (
                        <span className="text-slate-400">-</span>
                      )}
                    </td>

                    <td className="px-4 py-3.5 text-slate-500 text-[11px]">
                      {formatJalali(call.started_at, true)}
                    </td>

                    <td className="px-4 py-3.5 text-center">
                      {call.has_recording ? (
                        <Button
                          size="sm"
                          variant="outline"
                          icon={<Volume2 className="h-3.5 w-3.5 text-blue-600" />}
                          onClick={() => handlePlayRecording(call)}
                        >
                          پخش
                        </Button>
                      ) : (
                        <span className="text-[11px] text-slate-400">ندارد</span>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </Card>

      {/* Signed Recording Audio Modal */}
      <Modal
        isOpen={!!audioModalUrl}
        onClose={() => {
          setAudioModalUrl(null)
          setAudioCallInfo(null)
        }}
        title={fa.calls.playRecording}
        description={
          audioCallInfo
            ? `ضبط مکالمه شماره ${toPersianDigits(audioCallInfo.caller_number)} (لینک رمزنگاری‌شده موقت)`
            : ''
        }
      >
        {audioModalUrl && (
          <div className="space-y-4 py-2">
            <audio controls autoPlay src={audioModalUrl} className="w-full rounded-xl" />
            <p className="text-[11px] text-slate-400 text-center">
              این لینک با امضای دیجیتال (Signed URL) صادر شده و پس از ۱۵ دقیقه منقضی می‌شود.
            </p>
          </div>
        )}
      </Modal>
    </div>
  )
}
