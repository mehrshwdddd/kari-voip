import React, { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import {
  Megaphone,
  Plus,
  Play,
  Pause,
  BarChart3,
  ShieldAlert,
  Search,
  ArrowLeft,
  ArrowRight,
  Clock,
  Users,
  CheckCircle2,
} from 'lucide-react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { apiClient } from '../../../lib/api/client'
import { Card } from '../../../components/ui/Card'
import { Button } from '../../../components/ui/Button'
import { Input } from '../../../components/ui/Input'
import { Badge } from '../../../components/ui/Badge'
import { Modal } from '../../../components/ui/Modal'
import { EmptyState } from '../../../components/ui/EmptyState'
import { FileUpload } from '../../../components/devigner/FileUpload'
import { DeleteButton } from '../../../components/devigner/DeleteButton'
import { DncModal } from '../components/DncModal'
import { toPersianDigits } from '../../../lib/numerals'
import { formatJalali } from '../../../lib/jalali'
import { fa } from '../../../i18n/fa'
import type { Campaign, ContactTag, PaginatedResponse } from '../../../types'

export const CampaignsPage: React.FC = () => {
  const navigate = useNavigate()
  const queryClient = useQueryClient()

  const [search, setSearch] = useState('')
  const [statusFilter, setStatusFilter] = useState('')
  const [createOpen, setCreateOpen] = useState(false)
  const [dncOpen, setDncOpen] = useState(false)
  const [step, setStep] = useState<1 | 2 | 3>(1)

  // Step 1 form state
  const [name, setName] = useState('')
  const [description, setDescription] = useState('')
  const [type, setType] = useState<'audio' | 'tts' | 'ivr'>('audio')
  const [audioPath, setAudioPath] = useState('campaigns/welcome_message.wav')
  const [ttsText, setTtsText] = useState('')

  // Step 2 form state
  const [audienceMode, setAudienceMode] = useState<'crm' | 'csv' | 'manual'>('crm')
  const [selectedTagId, setSelectedTagId] = useState('')
  const [csvContent, setCsvContent] = useState('')
  const [manualNumbers, setManualNumbers] = useState('09121112233\n09123334455')

  // Step 3 form state
  const [allowedHoursStart, setAllowedHoursStart] = useState('09:00')
  const [allowedHoursEnd, setAllowedHoursEnd] = useState('20:00')
  const [maxConcurrent, setMaxConcurrent] = useState('5')
  const [maxRetries, setMaxRetries] = useState('2')
  const [humanTransfer, setHumanTransfer] = useState(true)
  const [recordCalls, setRecordCalls] = useState(true)
  const [formError, setFormError] = useState<string | null>(null)

  const { data: campaignsData, isLoading } = useQuery<PaginatedResponse<Campaign>>({
    queryKey: ['campaigns-list', search, statusFilter],
    queryFn: async () => {
      const params = new URLSearchParams()
      if (search) params.append('search', search)
      if (statusFilter) params.append('status', statusFilter)
      const res = await apiClient.get(`/campaigns?${params.toString()}`)
      return res.data
    },
  })

  const { data: tagsData } = useQuery<{ tags: ContactTag[] }>({
    queryKey: ['contact-tags'],
    queryFn: async () => {
      const res = await apiClient.get('/tags')
      return res.data
    },
  })

  const createMutation = useMutation({
    mutationFn: async (payload: any) => {
      const res = await apiClient.post('/campaigns', payload)
      return res.data
    },
    onSuccess: (res) => {
      queryClient.invalidateQueries({ queryKey: ['campaigns-list'] })
      setCreateOpen(false)
      navigate(`/campaigns/${res.campaign.id}`)
    },
    onError: (err: any) => {
      setFormError(err.response?.data?.message || fa.common.error)
    },
  })

  const actionMutation = useMutation({
    mutationFn: async ({ id, action }: { id: number; action: 'start' | 'pause' | 'resume' }) => {
      const res = await apiClient.post(`/campaigns/${id}/${action}`)
      return res.data
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['campaigns-list'] })
    },
  })

  const deleteMutation = useMutation({
    mutationFn: async (id: number) => {
      await apiClient.delete(`/campaigns/${id}`)
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['campaigns-list'] })
    },
  })

  const handleCsvFileSelect = (file: File) => {
    const reader = new FileReader()
    reader.onload = (ev) => {
      setCsvContent(String(ev.target?.result || ''))
    }
    reader.readAsText(file)
  }

  const handleSubmitCampaign = (e: React.FormEvent) => {
    e.preventDefault()
    setFormError(null)

    const payload: any = {
      name,
      description: description || null,
      type,
      audio_path: audioPath,
      tts_text: type === 'tts' ? ttsText : null,
      allowed_hours_start: allowedHoursStart,
      allowed_hours_end: allowedHoursEnd,
      max_concurrent_calls: parseInt(maxConcurrent) || 5,
      max_retry_count: parseInt(maxRetries) || 2,
      retry_interval_minutes: [15, 60, 180],
      human_transfer_enabled: humanTransfer,
      record_calls: recordCalls,
    }

    if (audienceMode === 'csv' && csvContent.trim()) {
      payload.csv_content = csvContent
    } else if (audienceMode === 'manual' && manualNumbers.trim()) {
      payload.phone_numbers = manualNumbers
        .split(/[\r\n,]+/)
        .map((s) => s.trim())
        .filter(Boolean)
    } else if (selectedTagId) {
      payload.tag_id = parseInt(selectedTagId)
    }

    createMutation.mutate(payload)
  }

  const renderStatusBadge = (st: Campaign['status']) => {
    switch (st) {
      case 'running':
        return <Badge variant="success">{fa.campaigns.statusRunning}</Badge>
      case 'paused':
        return <Badge variant="warning">{fa.campaigns.statusPaused}</Badge>
      case 'completed':
        return <Badge variant="primary">{fa.campaigns.statusCompleted}</Badge>
      case 'cancelled':
        return <Badge variant="danger">{fa.campaigns.statusCancelled}</Badge>
      case 'scheduled':
        return <Badge variant="warning">{fa.campaigns.statusScheduled}</Badge>
      default:
        return <Badge variant="neutral">{fa.campaigns.statusDraft}</Badge>
    }
  }

  const campaigns = campaignsData?.data || []

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
          <h1 className="text-xl font-black text-slate-900 dark:text-slate-100">
            {fa.campaigns.title}
          </h1>
          <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
            {fa.campaigns.subtitle}
          </p>
        </div>

        <div className="flex flex-wrap items-center gap-2">
          <Button
            variant="outline"
            size="md"
            icon={<ShieldAlert className="h-4 w-4 text-rose-500" />}
            onClick={() => setDncOpen(true)}
          >
            {fa.campaigns.dncButton}
          </Button>
          <Button
            size="md"
            icon={<Plus className="h-4 w-4" />}
            onClick={() => {
              setStep(1)
              setFormError(null)
              setCreateOpen(true)
            }}
          >
            {fa.campaigns.createNew}
          </Button>
        </div>
      </div>

      {/* Filters */}
      <Card className="p-4">
        <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
          <div className="sm:col-span-2">
            <Input
              placeholder={fa.campaigns.searchPlaceholder}
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              leadingIcon={<Search className="h-4 w-4" />}
            />
          </div>
          <select
            value={statusFilter}
            onChange={(e) => setStatusFilter(e.target.value)}
            className="w-full min-h-[44px] rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs text-slate-900 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"
          >
            <option value="">{fa.campaigns.allStatuses}</option>
            <option value="draft">{fa.campaigns.statusDraft}</option>
            <option value="running">{fa.campaigns.statusRunning}</option>
            <option value="paused">{fa.campaigns.statusPaused}</option>
            <option value="completed">{fa.campaigns.statusCompleted}</option>
          </select>
        </div>
      </Card>

      {/* Campaigns List */}
      {isLoading ? (
        <div className="p-12 text-center text-xs text-slate-400">
          {fa.common.loading}
        </div>
      ) : campaigns.length === 0 ? (
        <EmptyState
          icon={<Megaphone className="h-10 w-10 text-blue-500 stroke-1" />}
          title={fa.campaigns.noCampaignsFound}
          actionLabel={fa.campaigns.createNew}
          onAction={() => setCreateOpen(true)}
        />
      ) : (
        <div className="grid grid-cols-1 gap-4">
          {campaigns.map((camp) => (
            <Card key={camp.id} className="p-5">
              <div className="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                <div className="space-y-1.5">
                  <div className="flex flex-wrap items-center gap-2.5">
                    <h3
                      onClick={() => navigate(`/campaigns/${camp.id}`)}
                      className="text-base font-black text-slate-900 dark:text-slate-100 hover:text-blue-600 cursor-pointer transition-colors"
                    >
                      {camp.name}
                    </h3>
                    {renderStatusBadge(camp.status)}
                    <Badge variant="neutral" size="sm">
                      {camp.type === 'tts'
                        ? fa.campaigns.typeTts
                        : camp.type === 'ivr'
                        ? fa.campaigns.typeIvr
                        : fa.campaigns.typeAudio}
                    </Badge>
                  </div>

                  {camp.description && (
                    <p className="text-xs text-slate-500 dark:text-slate-400">
                      {camp.description}
                    </p>
                  )}

                  <div className="flex flex-wrap items-center gap-4 text-[11px] text-slate-400 pt-1">
                    <span className="flex items-center gap-1">
                      <Clock className="h-3.5 w-3.5" />
                      ساعات مجاز: {toPersianDigits(camp.allowed_hours_start)} تا{' '}
                      {toPersianDigits(camp.allowed_hours_end)}
                    </span>
                    <span className="flex items-center gap-1">
                      <Users className="h-3.5 w-3.5" />
                      مخاطبین هدف: {toPersianDigits(camp.total_contacts)} نفر
                    </span>
                    <span className="flex items-center gap-1 text-emerald-600 font-bold">
                      <CheckCircle2 className="h-3.5 w-3.5" />
                      پاسخ موفق: {toPersianDigits(camp.success_count)}
                    </span>
                  </div>
                </div>

                {/* Progress Bar & Action Buttons */}
                <div className="flex flex-col sm:flex-row lg:items-center gap-4">
                  <div className="w-full sm:w-44">
                    <div className="flex items-center justify-between text-xs font-bold mb-1">
                      <span className="text-slate-500">{fa.campaigns.progressLabel}</span>
                      <span className="text-blue-600">
                        %{toPersianDigits(camp.progress_percentage)}
                      </span>
                    </div>
                    <div className="w-full h-2 rounded-full bg-slate-100 dark:bg-slate-800 overflow-hidden">
                      <div
                        className="h-full bg-blue-600 transition-all duration-300"
                        style={{ width: `${camp.progress_percentage}%` }}
                      />
                    </div>
                  </div>

                  <div className="flex items-center gap-2">
                    {camp.status === 'draft' || camp.status === 'scheduled' ? (
                      <Button
                        size="sm"
                        className="bg-emerald-600 hover:bg-emerald-700"
                        icon={<Play className="h-3.5 w-3.5" />}
                        onClick={() =>
                          actionMutation.mutate({ id: camp.id, action: 'start' })
                        }
                      >
                        شروع
                      </Button>
                    ) : camp.status === 'running' ? (
                      <Button
                        size="sm"
                        variant="secondary"
                        icon={<Pause className="h-3.5 w-3.5" />}
                        onClick={() =>
                          actionMutation.mutate({ id: camp.id, action: 'pause' })
                        }
                      >
                        توقف
                      </Button>
                    ) : camp.status === 'paused' ? (
                      <Button
                        size="sm"
                        icon={<Play className="h-3.5 w-3.5" />}
                        onClick={() =>
                          actionMutation.mutate({ id: camp.id, action: 'resume' })
                        }
                      >
                        ادامه
                      </Button>
                    ) : null}

                    <Button
                      size="sm"
                      variant="outline"
                      icon={<BarChart3 className="h-3.5 w-3.5 text-blue-600" />}
                      onClick={() => navigate(`/campaigns/${camp.id}`)}
                    >
                      داشبورد و گزارش
                    </Button>

                    <DeleteButton
                      size="sm"
                      onConfirm={() => deleteMutation.mutate(camp.id)}
                    />
                  </div>
                </div>
              </div>
            </Card>
          ))}
        </div>
      )}

      {/* Multi-Step Create Campaign Modal */}
      <Modal
        isOpen={createOpen}
        onClose={() => setCreateOpen(false)}
        title={fa.campaigns.createNew}
        description="طراحی و راه‌اندازی کمپین تماس صوتی انبوه در ۳ گام ساده"
        maxWidth="xl"
      >
        {/* Stepper Header */}
        <div className="grid grid-cols-3 gap-2 mb-6 p-1.5 rounded-2xl bg-slate-100 dark:bg-slate-800 text-center text-xs font-bold">
          <button
            type="button"
            onClick={() => setStep(1)}
            className={`py-2 rounded-xl transition-all cursor-pointer ${
              step === 1
                ? 'bg-white dark:bg-slate-900 text-blue-600 shadow-xs'
                : 'text-slate-500'
            }`}
          >
            {fa.campaigns.step1Title}
          </button>
          <button
            type="button"
            onClick={() => setStep(2)}
            className={`py-2 rounded-xl transition-all cursor-pointer ${
              step === 2
                ? 'bg-white dark:bg-slate-900 text-blue-600 shadow-xs'
                : 'text-slate-500'
            }`}
          >
            {fa.campaigns.step2Title}
          </button>
          <button
            type="button"
            onClick={() => setStep(3)}
            className={`py-2 rounded-xl transition-all cursor-pointer ${
              step === 3
                ? 'bg-white dark:bg-slate-900 text-blue-600 shadow-xs'
                : 'text-slate-500'
            }`}
          >
            {fa.campaigns.step3Title}
          </button>
        </div>

        <form onSubmit={handleSubmitCampaign} className="space-y-4">
          {formError && (
            <div className="p-3 rounded-xl bg-rose-50 text-xs text-rose-600 border border-rose-200">
              {formError}
            </div>
          )}

          {/* Step 1: Content & Type */}
          {step === 1 && (
            <div className="space-y-4">
              <Input
                label={fa.campaigns.nameLabel}
                placeholder="مثال: اطلاع‌رسانی جشنواره فروش ویژه"
                value={name}
                onChange={(e) => setName(e.target.value)}
                required
              />

              <div>
                <label className="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1.5">
                  نوع پخش صوتی کمپین
                </label>
                <select
                  value={type}
                  onChange={(e: any) => setType(e.target.value)}
                  className="w-full min-h-[44px] rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs text-slate-900 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"
                >
                  <option value="audio">{fa.campaigns.typeAudio}</option>
                  <option value="tts">{fa.campaigns.typeTts}</option>
                  <option value="ivr">{fa.campaigns.typeIvr}</option>
                </select>
              </div>

              {type === 'tts' ? (
                <div>
                  <label className="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1.5">
                    متن پیام صوتی فارسی (TTS)
                  </label>
                  <textarea
                    rows={3}
                    placeholder={fa.campaigns.ttsPlaceholder}
                    value={ttsText}
                    onChange={(e) => setTtsText(e.target.value)}
                    className="w-full rounded-xl border border-slate-300 bg-white p-3 text-xs text-slate-900 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"
                  />
                </div>
              ) : (
                <div>
                  <label className="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1.5">
                    {fa.campaigns.uploadAudioLabel}
                  </label>
                  <FileUpload
                    accept="audio/*,.wav,.mp3"
                    onFileSelect={(file) => setAudioPath(`campaigns/${file.name}`)}
                  />
                </div>
              )}

              <div>
                <label className="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1.5">
                  {fa.campaigns.descLabel}
                </label>
                <textarea
                  rows={2}
                  value={description}
                  onChange={(e) => setDescription(e.target.value)}
                  className="w-full rounded-xl border border-slate-300 bg-white p-3 text-xs text-slate-900 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"
                />
              </div>

              <div className="flex justify-end pt-3 border-t border-slate-100 dark:border-slate-800">
                <Button
                  type="button"
                  size="md"
                  icon={<ArrowLeft className="h-4 w-4" />}
                  onClick={() => {
                    if (!name.trim()) {
                      setFormError('لطفاً عنوان کمپین را وارد نمایید.')
                      return
                    }
                    setFormError(null)
                    setStep(2)
                  }}
                >
                  مرحله بعد
                </Button>
              </div>
            </div>
          )}

          {/* Step 2: Target Contacts */}
          {step === 2 && (
            <div className="space-y-4">
              <div className="grid grid-cols-3 gap-2">
                {[
                  { id: 'crm' as const, label: 'انتخاب از بانک CRM' },
                  { id: 'csv' as const, label: 'آپلود فایل CSV / اکسل' },
                  { id: 'manual' as const, label: 'ورود دستی شماره‌ها' },
                ].map((m) => (
                  <button
                    key={m.id}
                    type="button"
                    onClick={() => setAudienceMode(m.id)}
                    className={`p-3 rounded-xl border text-xs font-bold cursor-pointer ${
                      audienceMode === m.id
                        ? 'border-blue-600 bg-blue-50/40 dark:bg-blue-950/30 text-blue-600'
                        : 'border-slate-200 dark:border-slate-800'
                    }`}
                  >
                    {m.label}
                  </button>
                ))}
              </div>

              {audienceMode === 'crm' && (
                <div>
                  <label className="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1.5">
                    فیلتر بر اساس برچسب مخاطبین (اختیاری)
                  </label>
                  <select
                    value={selectedTagId}
                    onChange={(e) => setSelectedTagId(e.target.value)}
                    className="w-full min-h-[44px] rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs text-slate-900 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"
                  >
                    <option value="">همه مخاطبین فعال موجود در CRM</option>
                    {tagsData?.tags?.map((t) => (
                      <option key={t.id} value={t.id}>
                        برچسب: {t.name}
                      </option>
                    ))}
                  </select>
                </div>
              )}

              {audienceMode === 'csv' && (
                <div>
                  <label className="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1.5">
                    {fa.campaigns.uploadContactsLabel}
                  </label>
                  <FileUpload
                    accept=".csv,.txt"
                    onFileSelect={handleCsvFileSelect}
                  />
                </div>
              )}

              {audienceMode === 'manual' && (
                <div>
                  <label className="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1.5">
                    {fa.campaigns.manualNumbersLabel}
                  </label>
                  <textarea
                    rows={4}
                    dir="ltr"
                    value={manualNumbers}
                    onChange={(e) => setManualNumbers(e.target.value)}
                    className="w-full rounded-xl border border-slate-300 bg-white p-3 text-xs font-mono text-slate-900 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"
                  />
                </div>
              )}

              <div className="flex items-center justify-between pt-3 border-t border-slate-100 dark:border-slate-800">
                <Button
                  type="button"
                  variant="outline"
                  size="md"
                  icon={<ArrowRight className="h-4 w-4" />}
                  onClick={() => setStep(1)}
                >
                  مرحله قبل
                </Button>
                <Button
                  type="button"
                  size="md"
                  icon={<ArrowLeft className="h-4 w-4" />}
                  onClick={() => setStep(3)}
                >
                  مرحله بعد
                </Button>
              </div>
            </div>
          )}

          {/* Step 3: Schedule, Retries & DTMF */}
          {step === 3 && (
            <div className="space-y-4">
              <div className="grid grid-cols-2 gap-4">
                <Input
                  label={fa.campaigns.allowedHoursStart}
                  type="time"
                  dir="ltr"
                  value={allowedHoursStart}
                  onChange={(e) => setAllowedHoursStart(e.target.value)}
                />
                <Input
                  label={fa.campaigns.allowedHoursEnd}
                  type="time"
                  dir="ltr"
                  value={allowedHoursEnd}
                  onChange={(e) => setAllowedHoursEnd(e.target.value)}
                />
              </div>

              <div className="grid grid-cols-2 gap-4">
                <Input
                  label={fa.campaigns.maxConcurrency}
                  type="number"
                  dir="ltr"
                  min={1}
                  max={100}
                  value={maxConcurrent}
                  onChange={(e) => setMaxConcurrent(e.target.value)}
                />
                <Input
                  label={fa.campaigns.maxRetries}
                  type="number"
                  dir="ltr"
                  min={0}
                  max={10}
                  value={maxRetries}
                  onChange={(e) => setMaxRetries(e.target.value)}
                />
              </div>

              <div className="p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200 dark:border-slate-800 space-y-2.5 text-xs">
                <div className="font-bold text-slate-700 dark:text-slate-300">
                  کلیدهای تعاملی و قوانین امنیتی (DTMF & DNC):
                </div>
                <label className="flex items-center gap-2 cursor-pointer">
                  <input
                    type="checkbox"
                    checked={humanTransfer}
                    onChange={(e) => setHumanTransfer(e.target.checked)}
                  />
                  <span>فشردن کلید ۱: انتقال مستقیم تماس‌گیرنده به صف منشی / اپراتور فروش</span>
                </label>
                <label className="flex items-center gap-2 cursor-pointer">
                  <input
                    type="checkbox"
                    checked={recordCalls}
                    onChange={(e) => setRecordCalls(e.target.checked)}
                  />
                  <span>ضبط خودکار مکالمات در صورت انتقال به اپراتور</span>
                </label>
                <div className="text-[11px] text-emerald-700 dark:text-emerald-400 font-medium">
                  ✓ فشردن کلید ۹ توسط مخاطب به‌صورت خودکار شماره را به لیست سیاه عدم تماس (DNC) منتقل می‌کند.
                </div>
              </div>

              <div className="flex items-center justify-between pt-3 border-t border-slate-100 dark:border-slate-800">
                <Button
                  type="button"
                  variant="outline"
                  size="md"
                  onClick={() => setStep(2)}
                >
                  مرحله قبل
                </Button>
                <Button
                  type="submit"
                  size="md"
                  isLoading={createMutation.isPending}
                >
                  ایجاد و ذخیره کمپین
                </Button>
              </div>
            </div>
          )}
        </form>
      </Modal>

      {/* DNC Management Modal */}
      <DncModal isOpen={dncOpen} onClose={() => setDncOpen(false)} />
    </div>
  )
}
