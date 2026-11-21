import React, { useState } from 'react'
import { ShieldAlert, Plus, PhoneOff } from 'lucide-react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { apiClient } from '../../../lib/api/client'
import { Modal } from '../../../components/ui/Modal'
import { Button } from '../../../components/ui/Button'
import { Input } from '../../../components/ui/Input'
import { Badge } from '../../../components/ui/Badge'
import { DeleteButton } from '../../../components/devigner/DeleteButton'
import { toPersianDigits } from '../../../lib/numerals'
import { formatJalali } from '../../../lib/jalali'
import { fa } from '../../../i18n/fa'
import type { DoNotCallEntry, PaginatedResponse } from '../../../types'

interface DncModalProps {
  isOpen: boolean
  onClose: () => void
}

export const DncModal: React.FC<DncModalProps> = ({ isOpen, onClose }) => {
  const queryClient = useQueryClient()
  const [phone, setPhone] = useState('')
  const [reason, setReason] = useState('')
  const [error, setError] = useState<string | null>(null)

  const { data, isLoading } = useQuery<PaginatedResponse<DoNotCallEntry>>({
    queryKey: ['dnc-list'],
    queryFn: async () => {
      const res = await apiClient.get('/dnc')
      return res.data
    },
    enabled: isOpen,
  })

  const addMutation = useMutation({
    mutationFn: async () => {
      const res = await apiClient.post('/dnc', {
        phone_number: phone,
        reason: reason || 'ثبت دستی در لیست سیاه',
        source: 'manual',
      })
      return res.data
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['dnc-list'] })
      setPhone('')
      setReason('')
      setError(null)
    },
    onError: (err: any) => {
      setError(err.response?.data?.message || fa.common.error)
    },
  })

  const deleteMutation = useMutation({
    mutationFn: async (id: number) => {
      await apiClient.delete(`/dnc/${id}`)
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['dnc-list'] })
    },
  })

  const handleAdd = (e: React.FormEvent) => {
    e.preventDefault()
    if (!phone.trim()) return
    addMutation.mutate()
  }

  const entries = data?.data || []

  return (
    <Modal
      isOpen={isOpen}
      onClose={onClose}
      title={fa.dnc.title}
      description={fa.dnc.subtitle}
      maxWidth="xl"
    >
      <div className="space-y-5">
        {/* Add phone to DNC form */}
        <form onSubmit={handleAdd} className="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200 dark:border-slate-800 space-y-3">
          {error && (
            <div className="p-2.5 rounded-xl bg-rose-50 text-xs text-rose-600">
              {error}
            </div>
          )}
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <Input
              label="شماره تلفن"
              dir="ltr"
              placeholder={fa.dnc.phonePlaceholder}
              value={phone}
              onChange={(e) => setPhone(e.target.value)}
              required
            />
            <Input
              label="علت ثبت"
              placeholder={fa.dnc.reasonPlaceholder}
              value={reason}
              onChange={(e) => setReason(e.target.value)}
            />
          </div>
          <div className="flex justify-end">
            <Button
              type="submit"
              size="sm"
              variant="danger"
              isLoading={addMutation.isPending}
              icon={<Plus className="h-4 w-4" />}
            >
              {fa.dnc.addPhone}
            </Button>
          </div>
        </form>

        {/* DNC List */}
        <div className="max-h-72 overflow-y-auto divide-y divide-slate-100 dark:divide-slate-800 border border-slate-200 dark:border-slate-800 rounded-2xl">
          {isLoading ? (
            <div className="p-6 text-center text-xs text-slate-400">
              {fa.common.loading}
            </div>
          ) : entries.length === 0 ? (
            <div className="p-8 text-center text-xs text-slate-400">
              هیچ شماره‌ای در لیست سیاه عدم تماس ثبت نشده است.
            </div>
          ) : (
            entries.map((entry) => (
              <div
                key={entry.id}
                className="p-3.5 flex items-center justify-between gap-3 bg-white dark:bg-slate-900"
              >
                <div className="flex items-center gap-3">
                  <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-rose-50 dark:bg-rose-950/50 text-rose-600 shrink-0">
                    <PhoneOff className="h-4 w-4" />
                  </div>
                  <div>
                    <div className="flex items-center gap-2">
                      <span className="font-mono font-bold text-xs text-slate-900 dark:text-slate-100">
                        {toPersianDigits(entry.phone_number)}
                      </span>
                      <Badge variant={entry.source === 'dtmf_opt_out' ? 'warning' : 'danger'} size="sm">
                        {entry.source === 'dtmf_opt_out'
                          ? fa.dnc.sourceDtmf
                          : fa.dnc.sourceManual}
                      </Badge>
                    </div>
                    <div className="text-[11px] text-slate-400 mt-0.5">
                      {entry.reason || '-'} • {formatJalali(entry.added_at)}
                    </div>
                  </div>
                </div>
                <DeleteButton
                  size="sm"
                  label="حذف از لیست"
                  onConfirm={() => deleteMutation.mutate(entry.id)}
                />
              </div>
            ))
          )}
        </div>
      </div>
    </Modal>
  )
}
