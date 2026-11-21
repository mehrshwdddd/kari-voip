import React, { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import {
  Search,
  Plus,
  Phone,
  Eye,
  Filter,
  User,
  Tag,
  Building,
} from 'lucide-react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { apiClient } from '../../../lib/api/client'
import { Card } from '../../../components/ui/Card'
import { Button } from '../../../components/ui/Button'
import { Input } from '../../../components/ui/Input'
import { Badge } from '../../../components/ui/Badge'
import { Modal } from '../../../components/ui/Modal'
import { EmptyState } from '../../../components/ui/EmptyState'
import { DeleteButton } from '../../../components/devigner/DeleteButton'
import { ContextMenu } from '../../../components/devigner/ContextMenu'
import { toPersianDigits } from '../../../lib/numerals'
import { formatToman } from '../../../lib/currency'
import { fa } from '../../../i18n/fa'
import type { Contact, Branch, ContactTag, PaginatedResponse } from '../../../types'

export const ContactsPage: React.FC = () => {
  const [search, setSearch] = useState('')
  const [selectedBranch, setSelectedBranch] = useState('')
  const [selectedStatus, setSelectedStatus] = useState('')
  const [isModalOpen, setIsModalOpen] = useState(false)
  const navigate = useNavigate()
  const queryClient = useQueryClient()

  // Form State for creating a new contact
  const [firstName, setFirstName] = useState('')
  const [lastName, setLastName] = useState('')
  const [phonePrimary, setPhonePrimary] = useState('')
  const [nationalId, setNationalId] = useState('')
  const [email, setEmail] = useState('')
  const [contactBranchId, setContactBranchId] = useState('')
  const [status, setStatus] = useState('active')
  const [notes, setNotes] = useState('')
  const [formError, setFormError] = useState<string | null>(null)

  // Fetch Contacts
  const { data: contactsData, isLoading } = useQuery<PaginatedResponse<Contact>>({
    queryKey: ['contacts', search, selectedBranch, selectedStatus],
    queryFn: async () => {
      const params = new URLSearchParams()
      if (search) params.append('search', search)
      if (selectedBranch) params.append('branch_id', selectedBranch)
      if (selectedStatus) params.append('status', selectedStatus)
      const res = await apiClient.get(`/contacts?${params.toString()}`)
      return res.data
    },
  })

  // Fetch Branches for filter & dropdown
  const { data: branchesData } = useQuery<{ branches: Branch[] }>({
    queryKey: ['branches-select'],
    queryFn: async () => {
      const res = await apiClient.get('/branches')
      return res.data
    },
  })

  // Create Contact Mutation
  const createMutation = useMutation({
    mutationFn: async (payload: any) => {
      const res = await apiClient.post('/contacts', payload)
      return res.data
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['contacts'] })
      queryClient.invalidateQueries({ queryKey: ['contacts-count'] })
      setIsModalOpen(false)
      resetForm()
    },
    onError: (err: any) => {
      setFormError(
        err.response?.data?.errors?.phone_primary?.[0] ||
        err.response?.data?.message ||
        fa.common.error
      )
    },
  })

  // Delete Contact Mutation
  const deleteMutation = useMutation({
    mutationFn: async (id: number) => {
      await apiClient.delete(`/contacts/${id}`)
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['contacts'] })
      queryClient.invalidateQueries({ queryKey: ['contacts-count'] })
    },
  })

  const resetForm = () => {
    setFirstName('')
    setLastName('')
    setPhonePrimary('')
    setNationalId('')
    setEmail('')
    setContactBranchId('')
    setStatus('active')
    setNotes('')
    setFormError(null)
  }

  const handleCreateContact = (e: React.FormEvent) => {
    e.preventDefault()
    setFormError(null)

    createMutation.mutate({
      first_name: firstName,
      last_name: lastName || null,
      phone_primary: phonePrimary,
      national_id: nationalId || null,
      email: email || null,
      branch_id: contactBranchId ? parseInt(contactBranchId) : null,
      status,
      notes: notes || null,
    })
  }

  const contacts = contactsData?.data || []

  return (
    <div className="space-y-6">
      {/* Page Header */}
      <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
          <h1 className="text-xl font-black text-slate-900 dark:text-slate-100">
            {fa.contacts.title}
          </h1>
          <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
            {fa.contacts.subtitle}
          </p>
        </div>
        <Button
          size="md"
          icon={<Plus className="h-4 w-4" />}
          onClick={() => {
            resetForm()
            setIsModalOpen(true)
          }}
        >
          {fa.contacts.addNew}
        </Button>
      </div>

      {/* Filter and Search Bar */}
      <Card className="p-4">
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
          {/* Search Input */}
          <div className="lg:col-span-2">
            <Input
              placeholder={fa.contacts.searchPlaceholder}
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              leadingIcon={<Search className="h-4 w-4" />}
            />
          </div>

          {/* Branch Filter */}
          <div>
            <select
              value={selectedBranch}
              onChange={(e) => setSelectedBranch(e.target.value)}
              className="w-full min-h-[44px] rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs text-slate-900 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 focus:border-blue-600 focus:outline-none"
            >
              <option value="">{fa.contacts.allBranches}</option>
              {branchesData?.branches?.map((branch) => (
                <option key={branch.id} value={branch.id}>
                  {branch.name}
                </option>
              ))}
            </select>
          </div>

          {/* Status Filter */}
          <div>
            <select
              value={selectedStatus}
              onChange={(e) => setSelectedStatus(e.target.value)}
              className="w-full min-h-[44px] rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs text-slate-900 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 focus:border-blue-600 focus:outline-none"
            >
              <option value="">همه وضعیت‌ها</option>
              <option value="active">{fa.contacts.statusActive}</option>
              <option value="vip">{fa.contacts.statusVip}</option>
              <option value="blocked">{fa.contacts.statusBlocked}</option>
              <option value="inactive">{fa.contacts.statusInactive}</option>
            </select>
          </div>
        </div>
      </Card>

      {/* Contacts Table / Content */}
      <Card className="p-0 overflow-hidden">
        {isLoading ? (
          <div className="p-12 text-center text-xs text-slate-400">
            {fa.common.loading}
          </div>
        ) : contacts.length === 0 ? (
          <div className="p-8">
            <EmptyState
              title={fa.contacts.noContactsFound}
              description={fa.contacts.emptySubtitle}
              actionLabel={fa.contacts.addNew}
              onAction={() => setIsModalOpen(true)}
            />
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-right text-xs">
              <thead className="bg-slate-50/80 dark:bg-slate-800/40 border-b border-slate-200/80 dark:border-slate-800 text-slate-500 font-bold">
                <tr>
                  <th className="px-4 py-3.5">مخاطب</th>
                  <th className="px-4 py-3.5">شماره تماس</th>
                  <th className="px-4 py-3.5">شعبه</th>
                  <th className="px-4 py-3.5">وضعیت</th>
                  <th className="px-4 py-3.5">برچسب‌ها</th>
                  <th className="px-4 py-3.5">کیف پول / بدهی</th>
                  <th className="px-4 py-3.5 text-center">عملیات</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                {contacts.map((contact) => (
                  <ContextMenu
                    key={contact.id}
                    items={[
                      {
                        id: 'view',
                        label: 'مشاهده پرونده کامل',
                        icon: <Eye className="h-4 w-4" />,
                        onClick: () => navigate(`/contacts/${contact.id}`),
                      },
                      {
                        id: 'delete',
                        label: 'حذف مخاطب',
                        danger: true,
                        onClick: () => deleteMutation.mutate(contact.id),
                      },
                    ]}
                  >
                    <tr
                      onClick={() => navigate(`/contacts/${contact.id}`)}
                      className="hover:bg-slate-50/80 dark:hover:bg-slate-800/50 transition-colors cursor-pointer"
                    >
                      {/* Name & Avatar */}
                      <td className="px-4 py-3.5">
                        <div className="flex items-center gap-3">
                          <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-blue-100 dark:bg-blue-950 text-blue-700 dark:text-blue-300 font-bold shrink-0">
                            {contact.first_name[0]}
                          </div>
                          <div>
                            <div className="font-bold text-slate-900 dark:text-slate-100">
                              {contact.full_name}
                            </div>
                            {contact.national_id && (
                              <div className="text-[11px] text-slate-400">
                                کدملی: {toPersianDigits(contact.national_id)}
                              </div>
                            )}
                          </div>
                        </div>
                      </td>

                      {/* Phone */}
                      <td className="px-4 py-3.5 font-mono text-slate-700 dark:text-slate-300 dir-ltr text-right">
                        {toPersianDigits(contact.phone_primary)}
                      </td>

                      {/* Branch */}
                      <td className="px-4 py-3.5 text-slate-500">
                        {contact.branch?.name || '-'}
                      </td>

                      {/* Status */}
                      <td className="px-4 py-3.5">
                        {contact.status === 'vip' ? (
                          <Badge variant="warning">{fa.contacts.statusVip}</Badge>
                        ) : contact.status === 'blocked' ? (
                          <Badge variant="danger">{fa.contacts.statusBlocked}</Badge>
                        ) : (
                          <Badge variant="success">{fa.contacts.statusActive}</Badge>
                        )}
                      </td>

                      {/* Tags */}
                      <td className="px-4 py-3.5">
                        <div className="flex flex-wrap gap-1">
                          {contact.tag_pivots && contact.tag_pivots.length > 0 ? (
                            contact.tag_pivots.map((t) => (
                              <span
                                key={t.id}
                                className="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-medium bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300"
                              >
                                {t.name}
                              </span>
                            ))
                          ) : (
                            <span className="text-slate-400 text-[11px]">-</span>
                          )}
                        </div>
                      </td>

                      {/* Debt / Balance */}
                      <td className="px-4 py-3.5">
                        <div className="text-[11px]">
                          {parseFloat(String(contact.total_debt)) > 0 ? (
                            <span className="text-rose-600 font-bold">
                              بدهی: {formatToman(contact.total_debt)}
                            </span>
                          ) : (
                            <span className="text-slate-500">
                              اعتبار: {formatToman(contact.credit_balance)}
                            </span>
                          )}
                        </div>
                      </td>

                      {/* Actions */}
                      <td
                        className="px-4 py-3.5 text-center"
                        onClick={(e) => e.stopPropagation()}
                      >
                        <div className="flex items-center justify-center gap-1.5">
                          <button
                            type="button"
                            onClick={() => navigate(`/contacts/${contact.id}`)}
                            title="مشاهده پرونده"
                            className="p-2 rounded-xl text-slate-400 hover:text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-950/40 transition-colors"
                          >
                            <Eye className="h-4 w-4" />
                          </button>
                          <DeleteButton
                            size="sm"
                            onConfirm={() => deleteMutation.mutate(contact.id)}
                          />
                        </div>
                      </td>
                    </tr>
                  </ContextMenu>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </Card>

      {/* Create Contact Modal */}
      <Modal
        isOpen={isModalOpen}
        onClose={() => setIsModalOpen(false)}
        title={fa.contacts.addNew}
        description="مشخصات مخاطب جدید را جهت ثبت در پرونده وارد نمایید"
        maxWidth="lg"
      >
        <form onSubmit={handleCreateContact} className="space-y-4">
          {formError && (
            <div className="p-3 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 text-xs text-rose-600">
              {formError}
            </div>
          )}

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <Input
              label={fa.contacts.firstName}
              value={firstName}
              onChange={(e) => setFirstName(e.target.value)}
              required
            />
            <Input
              label={fa.contacts.lastName}
              value={lastName}
              onChange={(e) => setLastName(e.target.value)}
            />
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <Input
              label={fa.contacts.phonePrimary}
              dir="ltr"
              placeholder="09123456789"
              value={phonePrimary}
              onChange={(e) => setPhonePrimary(e.target.value)}
              required
            />
            <Input
              label={fa.contacts.nationalId}
              dir="ltr"
              placeholder="0012345678"
              value={nationalId}
              onChange={(e) => setNationalId(e.target.value)}
            />
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label className="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1.5">
                {fa.contacts.branch}
              </label>
              <select
                value={contactBranchId}
                onChange={(e) => setContactBranchId(e.target.value)}
                className="w-full min-h-[44px] rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs text-slate-900 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"
              >
                <option value="">بدون شعبه (مرکزی / عمومی)</option>
                {branchesData?.branches?.map((b) => (
                  <option key={b.id} value={b.id}>
                    {b.name}
                  </option>
                ))}
              </select>
            </div>

            <div>
              <label className="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1.5">
                {fa.contacts.status}
              </label>
              <select
                value={status}
                onChange={(e) => setStatus(e.target.value)}
                className="w-full min-h-[44px] rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs text-slate-900 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"
              >
                <option value="active">{fa.contacts.statusActive}</option>
                <option value="vip">{fa.contacts.statusVip}</option>
                <option value="blocked">{fa.contacts.statusBlocked}</option>
                <option value="inactive">{fa.contacts.statusInactive}</option>
              </select>
            </div>
          </div>

          <div>
            <label className="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1.5">
              توضیحات و یادداشت اولیه
            </label>
            <textarea
              rows={3}
              value={notes}
              onChange={(e) => setNotes(e.target.value)}
              placeholder="توضیحات اضافی، سوابق یا نیازمندی‌های مشتری..."
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
            <Button
              type="submit"
              size="md"
              isLoading={createMutation.isPending}
            >
              {fa.common.save}
            </Button>
          </div>
        </form>
      </Modal>
    </div>
  )
}
