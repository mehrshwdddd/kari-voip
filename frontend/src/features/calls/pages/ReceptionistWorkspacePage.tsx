import React, { useState, useEffect } from 'react'
import { useNavigate } from 'react-router-dom'
import {
  Phone,
  PhoneIncoming,
  PhoneOutgoing,
  PhoneOff,
  Pause,
  Play,
  Mic,
  MicOff,
  Disc,
  Forward,
  UserCheck,
  CreditCard,
  AlertCircle,
  Award,
  FileText,
  Radio,
  Sparkles,
  CheckCircle2,
  Send,
  ExternalLink,
  ChevronUp,
} from 'lucide-react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { apiClient } from '../../../lib/api/client'
import { useCallSocket } from '../../../hooks/useCallSocket'
import { Card } from '../../../components/ui/Card'
import { Button } from '../../../components/ui/Button'
import { Input } from '../../../components/ui/Input'
import { Badge } from '../../../components/ui/Badge'
import { Modal } from '../../../components/ui/Modal'
import { EmptyState } from '../../../components/ui/EmptyState'
import { toPersianDigits } from '../../../lib/numerals'
import { formatToman } from '../../../lib/currency'
import { formatJalali } from '../../../lib/jalali'
import { fa } from '../../../i18n/fa'
import type { Call, CallDisposition } from '../../../types'

export const ReceptionistWorkspacePage: React.FC = () => {
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const { connected } = useCallSocket()

  const [dialNumber, setDialNumber] = useState('')
  const [selectedCallId, setSelectedCallId] = useState<number | null>(null)
  const [quickNote, setQuickNote] = useState('')
  const [noteType, setNoteType] = useState<'note' | 'task' | 'follow_up'>('note')
  const [transferModalOpen, setTransferModalOpen] = useState(false)
  const [transferTarget, setTransferTarget] = useState('105')
  const [mobileSheetOpen, setMobileSheetOpen] = useState(false)
  const [elapsedSeconds, setElapsedSeconds] = useState(0)
  const [toastMsg, setToastMsg] = useState<string | null>(null)

  const showToast = (msg: string) => {
    setToastMsg(msg)
    setTimeout(() => setToastMsg(null), 3500)
  }

  // Fetch active / ringing calls
  const { data: activeData } = useQuery<{ active_calls: Call[] }>({
    queryKey: ['active-calls'],
    queryFn: async () => {
      const res = await apiClient.get('/calls/active')
      return res.data
    },
    refetchInterval: 4000,
  })

  // Fetch dispositions
  const { data: dispositionsData } = useQuery<{ dispositions: CallDisposition[] }>({
    queryKey: ['call-dispositions'],
    queryFn: async () => {
      const res = await apiClient.get('/calls/dispositions/list')
      return res.data
    },
  })

  const activeCalls = activeData?.active_calls || []
  const currentCall =
    activeCalls.find((c) => c.id === selectedCallId) || activeCalls[0] || null

  // Live duration timer
  useEffect(() => {
    if (!currentCall) {
      setElapsedSeconds(0)
      return
    }
    const baseTime = currentCall.answered_at || currentCall.started_at
    const updateTimer = () => {
      const diff = Math.max(0, Math.floor((Date.now() - new Date(baseTime).getTime()) / 1000))
      setElapsedSeconds(diff)
    }
    updateTimer()
    const timer = setInterval(updateTimer, 1000)
    return () => clearInterval(timer)
  }, [currentCall])

  const formatTimer = (sec: number) => {
    const m = String(Math.floor(sec / 60)).padStart(2, '0')
    const s = String(sec % 60).padStart(2, '0')
    return toPersianDigits(`${m}:${s}`)
  }

  const refreshCalls = () => {
    queryClient.invalidateQueries({ queryKey: ['active-calls'] })
    queryClient.invalidateQueries({ queryKey: ['calls-history'] })
  }

  // Mutations for call controls
  const clickToCallMutation = useMutation({
    mutationFn: async (calleeNumber: string) => {
      const res = await apiClient.post('/calls/click-to-call', {
        callee_number: calleeNumber,
      })
      return res.data
    },
    onSuccess: (data) => {
      setDialNumber('')
      setSelectedCallId(data.call.id)
      showToast(data.message)
      refreshCalls()
    },
  })

  const simulateInboundMutation = useMutation({
    mutationFn: async () => {
      const res = await apiClient.post('/calls/simulate-inbound', {})
      return res.data
    },
    onSuccess: (data) => {
      setSelectedCallId(data.call.id)
      showToast(data.message)
      refreshCalls()
    },
  })

  const actionMutation = useMutation({
    mutationFn: async ({ callId, action, payload }: { callId: number; action: string; payload?: any }) => {
      const res = await apiClient.post(`/calls/${callId}/${action}`, payload || {})
      return res.data
    },
    onSuccess: (data) => {
      if (data?.message) showToast(data.message)
      refreshCalls()
    },
  })

  // Keyboard shortcuts for Receptionist speed
  useEffect(() => {
    const handleKeyDown = (e: KeyboardEvent) => {
      if (!currentCall) return
      const tag = (e.target as HTMLElement)?.tagName
      if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') return

      const key = e.key.toLowerCase()
      if (key === 'a' && currentCall.status === 'ringing') {
        e.preventDefault()
        actionMutation.mutate({ callId: currentCall.id, action: 'answer' })
      } else if (key === 'r' && currentCall.status === 'ringing') {
        e.preventDefault()
        actionMutation.mutate({ callId: currentCall.id, action: 'reject' })
      } else if (key === 'e' && currentCall.status !== 'ringing') {
        e.preventDefault()
        actionMutation.mutate({ callId: currentCall.id, action: 'end' })
      } else if (key === 'h' && currentCall.status !== 'ringing') {
        e.preventDefault()
        actionMutation.mutate({ callId: currentCall.id, action: 'hold' })
      } else if (key === 'm' && currentCall.status !== 'ringing') {
        e.preventDefault()
        actionMutation.mutate({ callId: currentCall.id, action: 'mute' })
      } else if (key === 'c' && currentCall.status !== 'ringing') {
        e.preventDefault()
        actionMutation.mutate({
          callId: currentCall.id,
          action: currentCall.is_recording ? 'recording/stop' : 'recording/start',
        })
      }
    }
    window.addEventListener('keydown', handleKeyDown)
    return () => window.removeEventListener('keydown', handleKeyDown)
  }, [currentCall, actionMutation])

  const handleDial = (e: React.FormEvent) => {
    e.preventDefault()
    if (!dialNumber.trim()) return
    clickToCallMutation.mutate(dialNumber.trim())
  }

  const handleAddNote = (e: React.FormEvent) => {
    e.preventDefault()
    if (!currentCall || !quickNote.trim()) return
    actionMutation.mutate(
      {
        callId: currentCall.id,
        action: 'note',
        payload: { content: quickNote, type: noteType },
      },
      {
        onSuccess: () => setQuickNote(''),
      }
    )
  }

  const handleTransfer = (e: React.FormEvent) => {
    e.preventDefault()
    if (!currentCall || !transferTarget.trim()) return
    actionMutation.mutate(
      {
        callId: currentCall.id,
        action: 'transfer',
        payload: { target: transferTarget.trim() },
      },
      {
        onSuccess: () => setTransferModalOpen(false),
      }
    )
  }

  const contact = currentCall?.contact

  return (
    <div className="space-y-6">
      {/* Header & Connection Status */}
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <div className="flex items-center gap-2.5">
            <h1 className="text-xl font-black text-slate-900 dark:text-slate-100">
              {fa.workspace.title}
            </h1>
            <span className="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
              <span className="h-2 w-2 rounded-full bg-emerald-500 animate-ping" />
              {connected ? fa.workspace.socketConnected : 'برخط'}
            </span>
          </div>
          <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
            {fa.workspace.subtitle}
          </p>
        </div>

        <div className="flex items-center gap-2">
          <Button
            variant="secondary"
            size="md"
            icon={<Sparkles className="h-4 w-4 text-amber-500" />}
            isLoading={simulateInboundMutation.isPending}
            onClick={() => simulateInboundMutation.mutate()}
          >
            {fa.workspace.simulateInbound}
          </Button>
        </div>
      </div>

      {toastMsg && (
        <div className="p-3.5 rounded-2xl bg-blue-600 text-white text-xs font-bold flex items-center justify-between shadow-lg shadow-blue-600/20">
          <div className="flex items-center gap-2">
            <CheckCircle2 className="h-4 w-4 shrink-0" />
            <span>{toastMsg}</span>
          </div>
        </div>
      )}

      {/* Dialpad / Click-to-Call Bar */}
      <Card className="p-4">
        <form onSubmit={handleDial} className="flex flex-col sm:flex-row items-center gap-3">
          <div className="flex-1 w-full">
            <Input
              dir="ltr"
              placeholder={fa.workspace.dialPlaceholder}
              value={dialNumber}
              onChange={(e) => setDialNumber(e.target.value)}
              leadingIcon={<PhoneOutgoing className="h-4 w-4 text-blue-500" />}
            />
          </div>
          <Button
            type="submit"
            size="md"
            isLoading={clickToCallMutation.isPending}
            icon={<Phone className="h-4 w-4" />}
            className="w-full sm:w-auto bg-emerald-600 hover:bg-emerald-700"
          >
            {fa.workspace.clickToCall}
          </Button>
        </form>
      </Card>

      {/* Main Workspace Area: Active Call + Screen Pop */}
      {!currentCall ? (
        <EmptyState
          icon={<Radio className="h-10 w-10 text-blue-500 stroke-1" />}
          title={fa.workspace.noActiveCalls}
          description={fa.workspace.noActiveCallsDesc}
          actionLabel={fa.workspace.simulateInbound}
          onAction={() => simulateInboundMutation.mutate()}
        />
      ) : (
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          {/* Right Column (2 cols): Screen Pop Customer 360 Snapshot */}
          <div className="lg:col-span-2 space-y-5">
            {/* Live Call Control Banner */}
            <Card
              className={`p-6 border-2 transition-all ${
                currentCall.status === 'ringing'
                  ? 'border-amber-400 bg-amber-50/30 dark:bg-amber-950/20'
                  : 'border-emerald-500/60 bg-white dark:bg-slate-900'
              }`}
            >
              <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 pb-5 border-b border-slate-100 dark:border-slate-800">
                <div className="flex items-center gap-4">
                  <div
                    className={`flex h-14 w-14 items-center justify-center rounded-2xl text-white shadow-lg ${
                      currentCall.status === 'ringing'
                        ? 'bg-amber-500 animate-bounce'
                        : 'bg-emerald-600'
                    }`}
                  >
                    <PhoneIncoming className="h-7 w-7" />
                  </div>
                  <div>
                    <div className="flex items-center gap-2">
                      <Badge
                        variant={
                          currentCall.status === 'ringing'
                            ? 'warning'
                            : currentCall.status === 'on_hold'
                            ? 'primary'
                            : 'success'
                        }
                      >
                        {currentCall.status === 'ringing'
                          ? fa.calls.statusRinging
                          : currentCall.status === 'on_hold'
                          ? fa.calls.statusOnHold
                          : fa.calls.statusActive}
                      </Badge>
                      {currentCall.is_recording && (
                        <Badge variant="danger" className="animate-pulse">
                          ● در حال ضبط
                        </Badge>
                      )}
                      {currentCall.queue && (
                        <span className="text-[11px] text-slate-400">
                          {currentCall.queue.name}
                        </span>
                      )}
                    </div>
                    <div className="text-lg font-black text-slate-900 dark:text-slate-100 mt-1 font-mono dir-ltr text-right">
                      {toPersianDigits(
                        currentCall.direction === 'inbound'
                          ? currentCall.caller_number
                          : currentCall.callee_number
                      )}
                    </div>
                  </div>
                </div>

                {/* Live Timer */}
                <div className="text-left">
                  <div className="text-2xl font-black font-mono text-slate-900 dark:text-slate-100">
                    {formatTimer(elapsedSeconds)}
                  </div>
                  <div className="text-[11px] text-slate-400">
                    {currentCall.direction === 'inbound' ? 'تماس ورودی' : 'تماس خروجی'}
                  </div>
                </div>
              </div>

              {/* Call Control Buttons */}
              <div className="flex flex-wrap items-center gap-2.5 pt-5">
                {currentCall.status === 'ringing' ? (
                  <>
                    <Button
                      size="md"
                      className="bg-emerald-600 hover:bg-emerald-700"
                      icon={<Phone className="h-4 w-4" />}
                      onClick={() =>
                        actionMutation.mutate({ callId: currentCall.id, action: 'answer' })
                      }
                    >
                      {fa.workspace.answerCall}
                    </Button>
                    <Button
                      size="md"
                      variant="danger"
                      icon={<PhoneOff className="h-4 w-4" />}
                      onClick={() =>
                        actionMutation.mutate({ callId: currentCall.id, action: 'reject' })
                      }
                    >
                      {fa.workspace.rejectCall}
                    </Button>
                  </>
                ) : (
                  <>
                    <Button
                      size="md"
                      variant="danger"
                      icon={<PhoneOff className="h-4 w-4" />}
                      onClick={() =>
                        actionMutation.mutate({ callId: currentCall.id, action: 'end' })
                      }
                    >
                      {fa.workspace.endCall}
                    </Button>

                    <Button
                      size="md"
                      variant={currentCall.status === 'on_hold' ? 'primary' : 'secondary'}
                      icon={
                        currentCall.status === 'on_hold' ? (
                          <Play className="h-4 w-4" />
                        ) : (
                          <Pause className="h-4 w-4" />
                        )
                      }
                      onClick={() =>
                        actionMutation.mutate({ callId: currentCall.id, action: 'hold' })
                      }
                    >
                      {currentCall.status === 'on_hold'
                        ? fa.workspace.unholdCall
                        : fa.workspace.holdCall}
                    </Button>

                    <Button
                      size="md"
                      variant={currentCall.is_muted ? 'primary' : 'secondary'}
                      icon={
                        currentCall.is_muted ? (
                          <MicOff className="h-4 w-4" />
                        ) : (
                          <Mic className="h-4 w-4" />
                        )
                      }
                      onClick={() =>
                        actionMutation.mutate({ callId: currentCall.id, action: 'mute' })
                      }
                    >
                      {currentCall.is_muted ? fa.workspace.unmuteCall : fa.workspace.muteCall}
                    </Button>

                    <Button
                      size="md"
                      variant={currentCall.is_recording ? 'danger' : 'secondary'}
                      icon={<Disc className="h-4 w-4" />}
                      onClick={() =>
                        actionMutation.mutate({
                          callId: currentCall.id,
                          action: currentCall.is_recording
                            ? 'recording/stop'
                            : 'recording/start',
                        })
                      }
                    >
                      {currentCall.is_recording ? fa.workspace.stopRec : fa.workspace.startRec}
                    </Button>

                    <Button
                      size="md"
                      variant="outline"
                      icon={<Forward className="h-4 w-4" />}
                      onClick={() => setTransferModalOpen(true)}
                    >
                      {fa.workspace.transferCall}
                    </Button>
                  </>
                )}
              </div>

              <div className="mt-4 pt-3 border-t border-slate-100 dark:border-slate-800 text-[11px] text-slate-400 flex items-center justify-between">
                <span>{fa.workspace.shortcutsHint}</span>
                <button
                  type="button"
                  onClick={() => setMobileSheetOpen(true)}
                  className="md:hidden text-blue-600 font-bold flex items-center gap-1"
                >
                  <ChevronUp className="h-3.5 w-3.5" />
                  <span>{fa.workspace.mobileQuickActions}</span>
                </button>
              </div>
            </Card>

            {/* Screen Pop: Customer 360 Snapshot */}
            <Card className="p-6">
              <div className="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-800">
                <div className="flex items-center gap-2">
                  <UserCheck className="h-5 w-5 text-blue-600" />
                  <h2 className="text-sm font-black text-slate-900 dark:text-slate-100">
                    {fa.workspace.screenPopBadge}
                  </h2>
                </div>
                {contact && (
                  <button
                    type="button"
                    onClick={() => navigate(`/contacts/${contact.id}`)}
                    className="inline-flex items-center gap-1 text-xs font-bold text-blue-600 hover:text-blue-700"
                  >
                    <span>مشاهده پرونده کامل ۳۶۰</span>
                    <ExternalLink className="h-3.5 w-3.5" />
                  </button>
                )}
              </div>

              {!contact ? (
                <div className="py-6 flex flex-col sm:flex-row items-center justify-between gap-4">
                  <div>
                    <div className="text-sm font-bold text-slate-800 dark:text-slate-200">
                      {fa.workspace.unknownCaller}
                    </div>
                    <p className="text-xs text-slate-500 mt-1">
                      شماره {toPersianDigits(currentCall.caller_number)} هنوز در بانک مشتریان ثبت نشده است.
                    </p>
                  </div>
                  <Button
                    size="sm"
                    onClick={() => navigate('/contacts')}
                  >
                    {fa.contacts.addNew}
                  </Button>
                </div>
              ) : (
                <div className="space-y-5 mt-4">
                  {/* Profile Snapshot Header */}
                  <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                    <div className="flex items-center gap-3.5">
                      <div className="flex h-14 w-14 items-center justify-center rounded-2xl bg-blue-600 text-white font-black text-lg">
                        {contact.first_name[0]}
                      </div>
                      <div>
                        <div className="flex items-center gap-2">
                          <span className="text-base font-black text-slate-900 dark:text-slate-100">
                            {contact.full_name}
                          </span>
                          {contact.is_vip && (
                            <Badge variant="warning">{fa.contacts.statusVip}</Badge>
                          )}
                        </div>
                        <div className="flex flex-wrap gap-1.5 mt-1.5">
                          {contact.tag_pivots?.map((tag) => (
                            <span
                              key={tag.id}
                              className="px-2 py-0.5 rounded-md text-[10px] font-bold bg-blue-50 text-blue-700 dark:bg-blue-950/60 dark:text-blue-300"
                            >
                              {tag.name}
                            </span>
                          ))}
                        </div>
                      </div>
                    </div>

                    {contact.notes && (
                      <div className="p-3 rounded-xl bg-amber-50/70 dark:bg-amber-950/30 border border-amber-200/80 dark:border-amber-800/60 text-xs text-amber-800 dark:text-amber-300 max-w-xs">
                        <span className="font-bold block mb-0.5">نکته مهم پرونده:</span>
                        {contact.notes}
                      </div>
                    )}
                  </div>

                  {/* Financial & Loyalty Snapshot */}
                  <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div className="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800">
                      <span className="text-[11px] text-slate-400 flex items-center gap-1">
                        <CreditCard className="h-3.5 w-3.5 text-blue-500" />
                        {fa.contacts.creditBalance}
                      </span>
                      <div className="text-xs font-bold text-slate-900 dark:text-slate-100 mt-1">
                        {formatToman(contact.credit_balance)}
                      </div>
                    </div>

                    <div className="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800">
                      <span className="text-[11px] text-slate-400 flex items-center gap-1">
                        <AlertCircle className="h-3.5 w-3.5 text-rose-500" />
                        {fa.contacts.totalDebt}
                      </span>
                      <div className="text-xs font-bold text-rose-600 mt-1">
                        {formatToman(contact.total_debt)}
                      </div>
                    </div>

                    <div className="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800">
                      <span className="text-[11px] text-slate-400 flex items-center gap-1">
                        <Award className="h-3.5 w-3.5 text-amber-500" />
                        {fa.contacts.loyaltyPoints}
                      </span>
                      <div className="text-xs font-bold text-amber-600 mt-1">
                        {toPersianDigits(contact.loyalty_points)} امتیاز
                      </div>
                    </div>
                  </div>

                  {/* Recent Notes in Screen Pop */}
                  {contact.notes_list && contact.notes_list.length > 0 && (
                    <div className="space-y-2">
                      <h4 className="text-xs font-bold text-slate-700 dark:text-slate-300">
                        آخرین یادداشت‌های پرونده:
                      </h4>
                      {contact.notes_list.slice(0, 3).map((n) => (
                        <div
                          key={n.id}
                          className="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/40 text-xs text-slate-600 dark:text-slate-300 flex items-start justify-between gap-2"
                        >
                          <span>{n.content}</span>
                          <span className="text-[10px] text-slate-400 shrink-0">
                            {formatJalali(n.created_at)}
                          </span>
                        </div>
                      ))}
                    </div>
                  )}
                </div>
              )}
            </Card>
          </div>

          {/* Left Column (1 col): Dispositions & In-Call Note */}
          <div className="space-y-5">
            {/* Call Outcome / Disposition Card */}
            <Card className="p-5">
              <h3 className="text-xs font-black text-slate-900 dark:text-slate-100 mb-3">
                {fa.workspace.dispositionTitle}
              </h3>
              <div className="flex flex-wrap gap-2">
                {dispositionsData?.dispositions?.map((disp) => {
                  const isSelected = currentCall.disposition === disp.name
                  return (
                    <button
                      key={disp.id}
                      type="button"
                      onClick={() =>
                        actionMutation.mutate({
                          callId: currentCall.id,
                          action: 'disposition',
                          payload: { disposition: disp.name },
                        })
                      }
                      className={`px-3 py-2 rounded-xl text-xs font-bold border transition-all cursor-pointer ${
                        isSelected
                          ? 'bg-blue-600 text-white border-blue-600 shadow-xs'
                          : 'bg-slate-50 dark:bg-slate-800/60 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-700 hover:border-blue-400'
                      }`}
                    >
                      {disp.name}
                    </button>
                  )
                })}
              </div>
            </Card>

            {/* Quick Note Form during Call */}
            <Card className="p-5">
              <h3 className="text-xs font-black text-slate-900 dark:text-slate-100 mb-3 flex items-center gap-1.5">
                <FileText className="h-4 w-4 text-blue-500" />
                <span>{fa.workspace.quickNoteTitle}</span>
              </h3>
              <form onSubmit={handleAddNote} className="space-y-3">
                <textarea
                  rows={3}
                  placeholder={fa.contacts.addNotePlaceholder}
                  value={quickNote}
                  onChange={(e) => setQuickNote(e.target.value)}
                  className="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/40 p-3 text-xs text-slate-900 dark:text-slate-100 focus:border-blue-600 focus:outline-none"
                />
                <div className="flex items-center justify-between gap-2">
                  <select
                    value={noteType}
                    onChange={(e: any) => setNoteType(e.target.value)}
                    className="rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-2.5 py-2 text-xs"
                  >
                    <option value="note">یادداشت تماس</option>
                    <option value="task">وظیفه فوری</option>
                    <option value="follow_up">پیگیری بعدی</option>
                  </select>
                  <Button
                    type="submit"
                    size="sm"
                    isLoading={actionMutation.isPending}
                    icon={<Send className="h-3.5 w-3.5" />}
                  >
                    {fa.contacts.addNoteButton}
                  </Button>
                </div>
              </form>
            </Card>
          </div>
        </div>
      )}

      {/* Transfer Call Modal */}
      <Modal
        isOpen={transferModalOpen}
        onClose={() => setTransferModalOpen(false)}
        title={fa.workspace.transferCall}
        description="شماره داخلی یا کد کارشناس مقصد را جهت انتقال خط وارد نمایید"
      >
        <form onSubmit={handleTransfer} className="space-y-4">
          <Input
            label="داخلی مقصد"
            dir="ltr"
            placeholder={fa.workspace.transferPlaceholder}
            value={transferTarget}
            onChange={(e) => setTransferTarget(e.target.value)}
            required
          />
          <div className="flex items-center justify-end gap-2 pt-2">
            <Button
              type="button"
              variant="outline"
              onClick={() => setTransferModalOpen(false)}
            >
              {fa.common.cancel}
            </Button>
            <Button type="submit" isLoading={actionMutation.isPending}>
              {fa.workspace.confirmTransfer}
            </Button>
          </div>
        </form>
      </Modal>

      {/* Mobile Bottom Sheet for Quick Call Actions */}
      <Modal
        isOpen={mobileSheetOpen}
        onClose={() => setMobileSheetOpen(false)}
        title={fa.workspace.mobileQuickActions}
      >
        {currentCall && (
          <div className="grid grid-cols-2 gap-2.5">
            <Button
              size="md"
              variant="secondary"
              onClick={() => {
                actionMutation.mutate({ callId: currentCall.id, action: 'hold' })
                setMobileSheetOpen(false)
              }}
            >
              {fa.workspace.holdCall}
            </Button>
            <Button
              size="md"
              variant="secondary"
              onClick={() => {
                actionMutation.mutate({ callId: currentCall.id, action: 'mute' })
                setMobileSheetOpen(false)
              }}
            >
              {fa.workspace.muteCall}
            </Button>
            <Button
              size="md"
              variant="secondary"
              onClick={() => {
                setMobileSheetOpen(false)
                setTransferModalOpen(true)
              }}
            >
              {fa.workspace.transferCall}
            </Button>
            <Button
              size="md"
              variant="danger"
              onClick={() => {
                actionMutation.mutate({ callId: currentCall.id, action: 'end' })
                setMobileSheetOpen(false)
              }}
            >
              {fa.workspace.endCall}
            </Button>
          </div>
        )}
      </Modal>
    </div>
  )
}
