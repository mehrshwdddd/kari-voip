import React, { useState } from 'react'
import { Plus, User, ShieldCheck, Mail, Phone, Lock } from 'lucide-react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
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
import type { User as UserType, Branch } from '../../../types'

export const UsersPage: React.FC = () => {
  const [isModalOpen, setIsModalOpen] = useState(false)
  const [name, setName] = useState('')
  const [email, setEmail] = useState('')
  const [phone, setPhone] = useState('')
  const [password, setPassword] = useState('')
  const [branchId, setBranchId] = useState('')
  const [role, setRole] = useState('receptionist')
  const [error, setError] = useState<string | null>(null)

  const queryClient = useQueryClient()

  // Fetch Users
  const { data: usersData, isLoading } = useQuery<{ data: UserType[] }>({
    queryKey: ['users-list'],
    queryFn: async () => {
      const res = await apiClient.get('/users')
      return res.data
    },
  })

  // Fetch Branches
  const { data: branchesData } = useQuery<{ branches: Branch[] }>({
    queryKey: ['branches-select'],
    queryFn: async () => {
      const res = await apiClient.get('/branches')
      return res.data
    },
  })

  // Create User Mutation
  const createMutation = useMutation({
    mutationFn: async (payload: any) => {
      const res = await apiClient.post('/users', payload)
      return res.data
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['users-list'] })
      setIsModalOpen(false)
      setName('')
      setEmail('')
      setPhone('')
      setPassword('')
    },
    onError: (err: any) => {
      setError(
        err.response?.data?.errors?.email?.[0] ||
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
      email,
      phone: phone || null,
      password,
      role,
      branch_id: branchId ? parseInt(branchId) : null,
      status: 'active',
    })
  }

  const users = usersData?.data || []

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
          <h1 className="text-xl font-black text-slate-900 dark:text-slate-100">
            {fa.users.title}
          </h1>
          <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
            {fa.users.subtitle}
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
          {fa.users.addNew}
        </Button>
      </div>

      {/* Users Table */}
      <Card className="p-0 overflow-hidden">
        {isLoading ? (
          <div className="p-12 text-center text-xs text-slate-400">
            {fa.common.loading}
          </div>
        ) : users.length === 0 ? (
          <div className="p-8">
            <EmptyState
              title="کاربری یافت نشد"
              description="اولین کاربر یا اپراتور سیستم را تعریف کنید."
              actionLabel={fa.users.addNew}
              onAction={() => setIsModalOpen(true)}
            />
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-right text-xs">
              <thead className="bg-slate-50/80 dark:bg-slate-800/40 border-b border-slate-200/80 dark:border-slate-800 text-slate-500 font-bold">
                <tr>
                  <th className="px-4 py-3.5">کاربر</th>
                  <th className="px-4 py-3.5">نقش</th>
                  <th className="px-4 py-3.5">شعبه</th>
                  <th className="px-4 py-3.5">اطلاعات تماس</th>
                  <th className="px-4 py-3.5">وضعیت</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                {users.map((u) => (
                  <tr key={u.id} className="hover:bg-slate-50/80 dark:hover:bg-slate-800/50 transition-colors">
                    <td className="px-4 py-3.5">
                      <div className="flex items-center gap-3">
                        <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-bold">
                          {u.name[0]}
                        </div>
                        <div>
                          <div className="font-bold text-slate-900 dark:text-slate-100">
                            {u.name}
                          </div>
                          <div className="text-[11px] text-slate-400 font-mono">
                            {u.email}
                          </div>
                        </div>
                      </div>
                    </td>

                    <td className="px-4 py-3.5">
                      <Badge variant="primary">
                        {u.roles?.[0] || 'کاربر'}
                      </Badge>
                    </td>

                    <td className="px-4 py-3.5 text-slate-500">
                      {u.branch?.name || 'دفتر مرکزی'}
                    </td>

                    <td className="px-4 py-3.5 font-mono text-slate-600 dark:text-slate-300">
                      {u.phone ? toPersianDigits(u.phone) : '-'}
                    </td>

                    <td className="px-4 py-3.5">
                      <Badge variant={u.status === 'active' ? 'success' : 'danger'}>
                        {u.status === 'active' ? 'فعال' : 'غیرفعال'}
                      </Badge>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </Card>

      {/* Create User Modal */}
      <Modal
        isOpen={isModalOpen}
        onClose={() => setIsModalOpen(false)}
        title={fa.users.addNew}
        description="مشخصات کارشناس یا منشی جدید را وارد فرمایید"
      >
        <form onSubmit={handleCreate} className="space-y-4">
          {error && (
            <div className="p-3 rounded-xl bg-rose-50 text-xs text-rose-600 border border-rose-200">
              {error}
            </div>
          )}

          <Input
            label="نام و نام خانوادگی"
            value={name}
            onChange={(e) => setName(e.target.value)}
            required
          />

          <Input
            label="پست الکترونیک (Email)"
            type="email"
            dir="ltr"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            required
          />

          <Input
            label="شماره موبایل"
            type="tel"
            dir="ltr"
            placeholder="0912..."
            value={phone}
            onChange={(e) => setPhone(e.target.value)}
          />

          <Input
            label="کلمه عبور"
            type="password"
            dir="ltr"
            placeholder="حداقل ۸ کاراکتر"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            required
          />

          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1.5">
                نقش کاربری
              </label>
              <select
                value={role}
                onChange={(e) => setRole(e.target.value)}
                className="w-full min-h-[44px] rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs text-slate-900 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"
              >
                <option value="owner">مدیر ارشد (مالک)</option>
                <option value="branch_manager">مدیر شعبه</option>
                <option value="receptionist">منشی / اپراتور</option>
                <option value="call_agent">کارشناس تماس</option>
                <option value="sales_agent">کارشناس فروش</option>
                <option value="accountant">حسابدار</option>
              </select>
            </div>

            <div>
              <label className="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1.5">
                شعبه تخصیصی
              </label>
              <select
                value={branchId}
                onChange={(e) => setBranchId(e.target.value)}
                className="w-full min-h-[44px] rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs text-slate-900 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"
              >
                <option value="">دفتر مرکزی (بدون محدودیت)</option>
                {branchesData?.branches?.map((b) => (
                  <option key={b.id} value={b.id}>
                    {b.name}
                  </option>
                ))}
              </select>
            </div>
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
