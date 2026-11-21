import React, { useState } from 'react'
import { useParams, useNavigate } from 'react-router-dom'
import {
  ArrowRight,
  Phone,
  Mail,
  Building,
  CreditCard,
  Award,
  AlertCircle,
  FileText,
  Clock,
  Pin,
  Send,
  Plus,
} from 'lucide-react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { apiClient } from '../../../lib/api/client'
import { Card } from '../../../components/ui/Card'
import { Button } from '../../../components/ui/Button'
import { Badge } from '../../../components/ui/Badge'
import { EmptyState } from '../../../components/ui/EmptyState'
import { FileUpload } from '../../../components/devigner/FileUpload'
import { toPersianDigits } from '../../../lib/numerals'
import { formatToman } from '../../../lib/currency'
import { formatJalali } from '../../../lib/jalali'
import { fa } from '../../../i18n/fa'
import type { Contact, TimelineItem } from '../../../types'

export const ContactProfilePage: React.FC = () => {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const [activeTab, setActiveTab] = useState<'notes' | 'timeline' | 'files'>('notes')
  const [newNoteContent, setNewNoteContent] = useState('')
  const [newNoteType, setNewNoteType] = useState<'note' | 'task' | 'follow_up'>('note')
  const [isPinned, setIsPinned] = useState(false)

  // Fetch Contact Customer 360
  const { data: contactData, isLoading } = useQuery<{ contact: Contact }>({
    queryKey: ['contact', id],
    queryFn: async () => {
      const res = await apiClient.get(`/contacts/${id}`)
      return res.data
    },
  })

  // Fetch Timeline
  const { data: timelineData } = useQuery<{ timeline: TimelineItem[] }>({
    queryKey: ['contact-timeline', id],
    queryFn: async () => {
      const res = await apiClient.get(`/contacts/${id}/timeline`)
      return res.data
    },
    enabled: activeTab === 'timeline',
  })

  // Add Note Mutation
  const addNoteMutation = useMutation({
    mutationFn: async (payload: any) => {
      const res = await apiClient.post(`/contacts/${id}/notes`, payload)
      return res.data
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['contact', id] })
      queryClient.invalidateQueries({ queryKey: ['contact-timeline', id] })
      setNewNoteContent('')
      setIsPinned(false)
    },
  })

  const handleAddNote = (e: React.FormEvent) => {
    e.preventDefault()
    if (!newNoteContent.trim()) return

    addNoteMutation.mutate({
      content: newNoteContent,
      type: newNoteType,
      is_pinned: isPinned,
    })
  }

  if (isLoading) {
    return (
      <div className="p-12 text-center text-xs text-slate-400">
        {fa.common.loading}
      </div>
    )
  }

  const contact = contactData?.contact
  if (!contact) {
    return (
      <div className="p-8">
        <EmptyState
          title="مخاطب یافت نشد"
          description="ممکن است این مخاطب حذف شده باشد یا دسترسی به آن برای شعبه شما محدود شده باشد."
          actionLabel="بازگشت به لیست مخاطبین"
          onAction={() => navigate('/contacts')}
        />
      </div>
    )
  }

  return (
    <div className="space-y-6">
      {/* Back button & Title */}
      <div className="flex items-center justify-between">
        <button
          type="button"
          onClick={() => navigate('/contacts')}
          className="inline-flex items-center gap-2 text-xs font-bold text-slate-500 hover:text-slate-800 dark:hover:text-slate-200 transition-colors"
        >
          <ArrowRight className="h-4 w-4" />
          <span>بازگشت به لیست مخاطبین</span>
        </button>

        <div className="flex items-center gap-2">
          {contact.is_vip && <Badge variant="warning">{fa.contacts.statusVip}</Badge>}
          <Badge variant={contact.status === 'active' ? 'success' : 'neutral'}>
            {contact.status === 'active' ? fa.contacts.statusActive : contact.status}
          </Badge>
        </div>
      </div>

      {/* Header Profile Card */}
      <Card className="p-6">
        <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-6">
          <div className="flex items-center gap-4">
            <div className="flex h-16 w-16 items-center justify-center rounded-2xl bg-blue-600 text-white font-black text-xl shadow-md shadow-blue-500/20">
              {contact.first_name[0]}
            </div>
            <div>
              <div className="flex items-center gap-3">
                <h1 className="text-lg font-black text-slate-900 dark:text-slate-100">
                  {contact.full_name}
                </h1>
                {contact.national_id && (
                  <span className="text-xs text-slate-400 font-mono">
                    کد ملی: {toPersianDigits(contact.national_id)}
                  </span>
                )}
              </div>
              <div className="flex flex-wrap items-center gap-4 mt-2 text-xs text-slate-500 dark:text-slate-400">
                <span className="flex items-center gap-1 font-mono text-slate-700 dark:text-slate-300">
                  <Phone className="h-3.5 w-3.5 text-blue-500" />
                  {toPersianDigits(contact.phone_primary)}
                </span>
                {contact.email && (
                  <span className="flex items-center gap-1">
                    <Mail className="h-3.5 w-3.5 text-slate-400" />
                    {contact.email}
                  </span>
                )}
                {contact.branch && (
                  <span className="flex items-center gap-1">
                    <Building className="h-3.5 w-3.5 text-amber-500" />
                    {contact.branch.name}
                  </span>
                )}
              </div>
            </div>
          </div>

          {/* Quick Call Action Button */}
          <div className="flex items-center gap-2 w-full sm:w-auto">
            <Button
              size="md"
              icon={<Phone className="h-4 w-4" />}
              className="w-full sm:w-auto bg-emerald-600 hover:bg-emerald-700 shadow-emerald-500/20"
              onClick={async () => {
                await apiClient.post('/calls/click-to-call', {
                  callee_number: contact.phone_primary,
                  contact_id: contact.id,
                })
                navigate('/workspace')
              }}
            >
              برقراری تماس صوتی
            </Button>
          </div>
        </div>

        {/* Financial and Loyalty Strip */}
        <div className="grid grid-cols-1 sm:grid-cols-3 gap-3 mt-6 pt-6 border-t border-slate-100 dark:border-slate-800">
          <div className="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800 flex items-center justify-between">
            <div>
              <span className="text-[11px] text-slate-400 flex items-center gap-1.5">
                <CreditCard className="h-3.5 w-3.5 text-blue-500" />
                {fa.contacts.creditBalance}
              </span>
              <div className="text-sm font-bold text-slate-900 dark:text-slate-100 mt-1">
                {formatToman(contact.credit_balance)}
              </div>
            </div>
            <Button
              size="sm"
              variant="outline"
              onClick={async () => {
                await apiClient.post(`/contacts/${contact.id}/wallet/topup`, {
                  amount: 500000,
                })
                queryClient.invalidateQueries({ queryKey: ['contact', id] })
              }}
            >
              + ۵۰۰ هزار تومان
            </Button>
          </div>

          <div className="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800">
            <span className="text-[11px] text-slate-400 flex items-center gap-1.5">
              <AlertCircle className="h-3.5 w-3.5 text-rose-500" />
              {fa.contacts.totalDebt}
            </span>
            <div className="text-sm font-bold text-rose-600 mt-1">
              {formatToman(contact.total_debt)}
            </div>
          </div>

          <div className="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800">
            <span className="text-[11px] text-slate-400 flex items-center gap-1.5">
              <Award className="h-3.5 w-3.5 text-amber-500" />
              {fa.contacts.loyaltyPoints}
            </span>
            <div className="text-sm font-bold text-amber-600 mt-1">
              {toPersianDigits(contact.loyalty_points)} امتیاز
            </div>
          </div>
        </div>
      </Card>

      {/* Tabs Row */}
      <div className="flex items-center gap-2 border-b border-slate-200 dark:border-slate-800">
        <button
          type="button"
          onClick={() => setActiveTab('notes')}
          className={`flex items-center gap-2 pb-3 px-3 text-xs font-bold border-b-2 transition-all cursor-pointer ${
            activeTab === 'notes'
              ? 'border-blue-600 text-blue-600 dark:text-blue-400'
              : 'border-transparent text-slate-400 hover:text-slate-600'
          }`}
        >
          <FileText className="h-4 w-4" />
          <span>{fa.contacts.notes}</span>
        </button>

        <button
          type="button"
          onClick={() => setActiveTab('timeline')}
          className={`flex items-center gap-2 pb-3 px-3 text-xs font-bold border-b-2 transition-all cursor-pointer ${
            activeTab === 'timeline'
              ? 'border-blue-600 text-blue-600 dark:text-blue-400'
              : 'border-transparent text-slate-400 hover:text-slate-600'
          }`}
        >
          <Clock className="h-4 w-4" />
          <span>{fa.contacts.timeline}</span>
        </button>

        <button
          type="button"
          onClick={() => setActiveTab('files')}
          className={`flex items-center gap-2 pb-3 px-3 text-xs font-bold border-b-2 transition-all cursor-pointer ${
            activeTab === 'files'
              ? 'border-blue-600 text-blue-600 dark:text-blue-400'
              : 'border-transparent text-slate-400 hover:text-slate-600'
          }`}
        >
          <Plus className="h-4 w-4" />
          <span>فایل‌ها و مدارک</span>
        </button>
      </div>

      {/* Tab 1: Notes & Tasks */}
      {activeTab === 'notes' && (
        <div className="space-y-4">
          {/* Add note card */}
          <Card className="p-4">
            <form onSubmit={handleAddNote} className="space-y-3">
              <textarea
                rows={2}
                placeholder={fa.contacts.addNotePlaceholder}
                value={newNoteContent}
                onChange={(e) => setNewNoteContent(e.target.value)}
                className="w-full rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/40 p-3 text-xs text-slate-900 dark:text-slate-100 focus:border-blue-600 focus:outline-none"
              />

              <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="flex items-center gap-3">
                  <select
                    value={newNoteType}
                    onChange={(e: any) => setNewNoteType(e.target.value)}
                    className="rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-2.5 py-1.5 text-xs text-slate-700 dark:text-slate-300"
                  >
                    <option value="note">یادداشت معمولی</option>
                    <option value="task">وظیفه و اقدام</option>
                    <option value="follow_up">پیگیری تماس</option>
                  </select>

                  <label className="flex items-center gap-1.5 text-xs text-slate-500 cursor-pointer">
                    <input
                      type="checkbox"
                      checked={isPinned}
                      onChange={(e) => setIsPinned(e.target.checked)}
                      className="rounded text-blue-600"
                    />
                    <span>سنجاق به بالا</span>
                  </label>
                </div>

                <Button
                  type="submit"
                  size="sm"
                  isLoading={addNoteMutation.isPending}
                  icon={<Send className="h-3.5 w-3.5" />}
                >
                  {fa.contacts.addNoteButton}
                </Button>
              </div>
            </form>
          </Card>

          {/* Notes list */}
          <div className="space-y-3">
            {contact.notes_list && contact.notes_list.length > 0 ? (
              contact.notes_list.map((note) => (
                <Card
                  key={note.id}
                  className={`p-4 ${
                    note.is_pinned
                      ? 'border-blue-200 dark:border-blue-900/60 bg-blue-50/20 dark:bg-blue-950/10'
                      : ''
                  }`}
                >
                  <div className="flex items-center justify-between text-xs text-slate-400 mb-2">
                    <div className="flex items-center gap-2">
                      {note.is_pinned && (
                        <Pin className="h-3.5 w-3.5 text-blue-500 fill-blue-500" />
                      )}
                      <span className="font-bold text-slate-700 dark:text-slate-300">
                        {note.user?.name || 'اپراتور'}
                      </span>
                    </div>
                    <span>{formatJalali(note.created_at, true)}</span>
                  </div>
                  <p className="text-xs text-slate-700 dark:text-slate-300 leading-relaxed whitespace-pre-wrap">
                    {note.content}
                  </p>
                </Card>
              ))
            ) : (
              <EmptyState
                title="یادداشتی ثبت نشده است"
                description="می‌توانید اولین یادداشت یا پیگیری برای این مشتری را ثبت کنید."
              />
            )}
          </div>
        </div>
      )}

      {/* Tab 2: Timeline */}
      {activeTab === 'timeline' && (
        <Card className="p-6">
          {timelineData?.timeline && timelineData.timeline.length > 0 ? (
            <div className="relative border-r-2 border-slate-100 dark:border-slate-800 pr-6 space-y-6">
              {timelineData.timeline.map((item) => (
                <div key={item.id} className="relative">
                  <div className="absolute -right-[31px] top-1 h-3 w-3 rounded-full bg-blue-600 ring-4 ring-white dark:ring-slate-900" />
                  <div className="text-xs font-bold text-slate-800 dark:text-slate-200">
                    {item.title}
                  </div>
                  <div className="text-[11px] text-slate-400 mt-0.5">
                    {formatJalali(item.created_at, true)} {item.author && `توسط ${item.author}`}
                  </div>
                  <p className="mt-2 text-xs text-slate-600 dark:text-slate-400 bg-slate-50 dark:bg-slate-800/40 p-3 rounded-xl border border-slate-100 dark:border-slate-800">
                    {item.content}
                  </p>
                </div>
              ))}
            </div>
          ) : (
            <EmptyState
              title="تایم‌لاین تعاملات خالی است"
              description="هنوز تماسی یا رخدادی برای این مخاطب ثبت نگردیده است."
            />
          )}
        </Card>
      )}

      {/* Tab 3: Files & Upload */}
      {activeTab === 'files' && (
        <Card className="p-6 space-y-4">
          <h3 className="text-xs font-bold text-slate-800 dark:text-slate-200 mb-2">
            بارگذاری پرونده یا مدارک مشتری
          </h3>
          <FileUpload
            onFileSelect={(file) => {
              alert(`فایل ${file.name} انتخاب شد و آماده ذخیره در پرونده است.`);
            }}
          />
        </Card>
      )}
    </div>
  )
}
