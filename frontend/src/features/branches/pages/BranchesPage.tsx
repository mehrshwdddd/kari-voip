import React, { useState } from 'react'
import { Plus, Building, Phone, MapPin, Users } from 'lucide-react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { apiClient } from '../../../lib/api/client'
import { Card } from '../../../components/ui/Card'
import { Button } from '../../../components/ui/Button'
import { Input } from '../../../components/ui/Input'
import { Badge } from '../../../components/ui/Badge'
import { Modal } from '../../../components/ui/Modal'
import { EmptyState } from '../../../components/ui/EmptyState'
import { toPersianDigits } from '../../../lib/numerals'
import { fa } from '../../../i18n/fa'
import type { Branch } from '../../../types'

export const BranchesPage: React.FC = () => {
  const [isModalOpen, setIsModalOpen] = useState(false)
  const [name, setName] = useState('')
  const [code, setCode] = useState('')
  const [phone, setPhone] = useState('')
  const [address, setAddress] = useState('')
  const [error, setError] = useState<string | null>(null)

  const queryClient = useQueryClient()

  const { data, isLoading } = useQuery<{ branches: Branch[] }>({
    queryKey: ['branches-list'],
    queryFn: async () => {
      const res = await apiClient.get('/branches')
      return res.data
    },
  })

  const createMutation = useMutation({
    mutationFn: async (payload: any) => {
      const res = await apiClient.post('/branches', payload)
      return res.data
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['branches-list'] })
      setIsModalOpen(false)
      setName('')
      setCode('')
      setPhone('')
      setAddress('')
    },
    onError: (err: any) => {
      setError(
        err.response?.data?.errors?.code?.[0] ||
        err.response?.data?.message ||
        fa.common.error
      )
    },
  })

  const handleCreate = (e: React.FormEvent) => {
    e.preventDefault()
    setError(null)
    createMutation.mutate({
      name,
      code: code.toUpperCase(),
      phone: phone || null,
      address: address || null,
      is_active: true,
    })
  }

  const branches = data?.branches || []

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
          <h1 className="text-xl font-black text-slate-900 dark:text-slate-100">
            {fa.branches.title}
          </h1>
          <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
            {fa.branches.subtitle}
          </p>
        </div>
        <Button
          size="md"
          icon={<Plus className="h-4 w-4" />}
          onClick={() => {
            setError(null)
            setIsModalOpen(true)
          }}
        >
          {fa.branches.addNew}
        </Button>
      </div>

      {/* Branches List */}
      {isLoading ? (
        <div className="p-12 text-center text-xs text-slate-400">
          {fa.common.loading}
        </div>
      ) : branches.length === 0 ? (
        <EmptyState
          title="شعبه‌ای تعریف نشده است"
          description="برای مدیریت مراکز، اولین شعبه خود را تعریف نمایید."
          actionLabel={fa.branches.addNew}
          onAction={() => setIsModalOpen(true)}
        />
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          {branches.map((branch) => (
            <Card key={branch.id} className="p-5 flex flex-col justify-between">
              <div>
                <div className="flex items-start justify-between">
                  <div className="flex items-center gap-3">
                    <div className="flex h-11 w-11 items-center justify-center rounded-2xl bg-amber-50 dark:bg-amber-950/50 text-amber-600">
                      <Building className="h-5 w-5" />
                    </div>
                    <div>
                      <h3 className="font-bold text-sm text-slate-900 dark:text-slate-100">
                        {branch.name}
                      </h3>
                      <span className="font-mono text-[11px] text-slate-400">
                        کد: {branch.code}
                      </span>
                    </div>
                  </div>
                  <Badge variant={branch.is_active ? 'success' : 'neutral'}>
                    {branch.is_active ? 'فعال' : 'غیرفعال'}
                  </Badge>
                </div>

                <div className="mt-4 space-y-2 text-xs text-slate-500 dark:text-slate-400">
                  {branch.phone && (
                    <div className="flex items-center gap-2">
                      <Phone className="h-3.5 w-3.5 text-slate-400" />
                      <span className="font-mono text-slate-700 dark:text-slate-300">
                        {toPersianDigits(branch.phone)}
                      </span>
                    </div>
                  )}
                  {branch.address && (
                    <div className="flex items-start gap-2">
                      <MapPin className="h-3.5 w-3.5 text-slate-400 shrink-0 mt-0.5" />
                      <span className="line-clamp-2">{branch.address}</span>
                    </div>
                  )}
                </div>
              </div>

              <div className="mt-5 pt-4 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between text-xs text-slate-400">
                <span className="flex items-center gap-1">
                  <Users className="h-3.5 w-3.5" />
                  <span>{toPersianDigits(branch.users_count ?? 0)} کاربر</span>
                </span>
                <span>{toPersianDigits(branch.contacts_count ?? 0)} مخاطب</span>
              </div>
            </Card>
          ))}
        </div>
      )}

      {/* Create Branch Modal */}
      <Modal
        isOpen={isModalOpen}
        onClose={() => setIsModalOpen(false)}
        title={fa.branches.addNew}
        description="مشخصات شعبه یا مرکز جدید را وارد نمایید"
      >
        <form onSubmit={handleCreate} className="space-y-4">
          {error && (
            <div className="p-3 rounded-xl bg-rose-50 text-xs text-rose-600 border border-rose-200">
              {error}
            </div>
          )}

          <Input
            label="نام شعبه / مرکز"
            value={name}
            onChange={(e) => setName(e.target.value)}
            placeholder="مثال: شعبه سعادت‌آباد"
            required
          />

          <Input
            label="کد شناسه شعبه (انگلیسی)"
            dir="ltr"
            value={code}
            onChange={(e) => setCode(e.target.value)}
            placeholder="مثال: WEST یا BR-02"
            required
          />

          <Input
            label="تلفن تماس شعبه"
            dir="ltr"
            value={phone}
            onChange={(e) => setPhone(e.target.value)}
            placeholder="021..."
          />

          <div>
            <label className="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1.5">
              آدرس
            </label>
            <textarea
              rows={2}
              value={address}
              onChange={(e) => setAddress(e.target.value)}
              className="w-full rounded-xl border border-slate-300 bg-white p-3 text-xs text-slate-900 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 focus:border-blue-600 focus:outline-none"
            />
          </div>

          <div className="flex items-center justify-end gap-2 pt-3 border-t border-slate-100 dark:border-slate-800">
            <Button
              type="button"
              variant="outline"
              size="md"
              onClick={() => setIsModalOpen(false)}
            >
              {fa.common.cancel}
            </Button>
            <Button type="submit" size="md" isLoading={createMutation.isPending}>
              {fa.common.save}
            </Button>
          </div>
        </form>
      </Modal>
    </div>
  )
}
