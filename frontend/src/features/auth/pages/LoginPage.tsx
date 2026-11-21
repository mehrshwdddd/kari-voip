import React, { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { PhoneCall, Lock, Mail, Phone, ArrowLeft, KeyRound } from 'lucide-react'
import { apiClient } from '../../../lib/api/client'
import { useAuthStore } from '../../../stores/authStore'
import { Button } from '../../../components/ui/Button'
import { Input } from '../../../components/ui/Input'
import { fa } from '../../../i18n/fa'

export const LoginPage: React.FC = () => {
  const [mode, setMode] = useState<'password' | 'otp'>('password')
  const [emailOrPhone, setEmailOrPhone] = useState('')
  const [password, setPassword] = useState('')
  const [phone, setPhone] = useState('')
  const [otpCode, setOtpCode] = useState('')
  const [otpSent, setOtpSent] = useState(false)
  const [isLoading, setIsLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [otpMessage, setOtpMessage] = useState<string | null>(null)

  const { setAuth } = useAuthStore()
  const navigate = useNavigate()

  const handlePasswordLogin = async (e: React.FormEvent) => {
    e.preventDefault()
    setIsLoading(true)
    setError(null)

    try {
      const response = await apiClient.post('/auth/login', {
        email: emailOrPhone,
        password,
      })

      if (response.data.requires_2fa) {
        // Handle 2FA if needed
        navigate('/2fa', { state: { token: response.data.two_factor_token } })
        return
      }

      setAuth(response.data.user, response.data.token)
      navigate('/dashboard')
    } catch (err: any) {
      setError(
        err.response?.data?.errors?.email?.[0] ||
        err.response?.data?.message ||
        fa.common.error
      )
    } finally {
      setIsLoading(false)
    }
  }

  const handleRequestOtp = async (e: React.FormEvent) => {
    e.preventDefault()
    setIsLoading(true)
    setError(null)

    try {
      const response = await apiClient.post('/auth/login/otp/request', { phone })
      setOtpSent(true)
      setOtpMessage(response.data.message)
    } catch (err: any) {
      setError(
        err.response?.data?.errors?.phone?.[0] ||
        err.response?.data?.message ||
        fa.common.error
      )
    } finally {
      setIsLoading(false)
    }
  }

  const handleVerifyOtp = async (e: React.FormEvent) => {
    e.preventDefault()
    setIsLoading(true)
    setError(null)

    try {
      const response = await apiClient.post('/auth/login/otp/verify', {
        phone,
        code: otpCode,
      })
      setAuth(response.data.user, response.data.token)
      navigate('/dashboard')
    } catch (err: any) {
      setError(
        err.response?.data?.errors?.code?.[0] ||
        err.response?.data?.message ||
        fa.auth.invalidOtp
      )
    } finally {
      setIsLoading(false)
    }
  }

  return (
    <div className="min-h-screen flex items-center justify-center p-4 bg-gradient-to-b from-slate-100 to-slate-200 dark:from-slate-950 dark:to-slate-900">
      <div className="w-full max-w-md bg-white dark:bg-slate-900 rounded-3xl p-8 border border-slate-200/80 dark:border-slate-800 shadow-2xl">
        {/* Logo and Brand */}
        <div className="flex flex-col items-center text-center mb-6">
          <div className="flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-tr from-blue-700 to-blue-500 text-white shadow-lg shadow-blue-500/25 mb-3">
            <PhoneCall className="h-7 w-7" />
          </div>
          <h1 className="text-xl font-black text-slate-900 dark:text-slate-100 tracking-tight">
            {fa.appName}
          </h1>
          <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
            {fa.auth.welcomeBack}
          </p>
        </div>

        {/* Tab switcher: Password vs OTP */}
        <div className="grid grid-cols-2 gap-1 p-1 bg-slate-100 dark:bg-slate-800 rounded-xl mb-6">
          <button
            type="button"
            onClick={() => {
              setMode('password')
              setError(null)
            }}
            className={`py-2 text-xs font-bold rounded-lg transition-all ${
              mode === 'password'
                ? 'bg-white dark:bg-slate-900 text-blue-600 dark:text-blue-400 shadow-xs'
                : 'text-slate-500 hover:text-slate-800 dark:text-slate-400'
            }`}
          >
            {fa.auth.tabPassword}
          </button>
          <button
            type="button"
            onClick={() => {
              setMode('otp')
              setError(null)
            }}
            className={`py-2 text-xs font-bold rounded-lg transition-all ${
              mode === 'otp'
                ? 'bg-white dark:bg-slate-900 text-blue-600 dark:text-blue-400 shadow-xs'
                : 'text-slate-500 hover:text-slate-800 dark:text-slate-400'
            }`}
          >
            {fa.auth.tabOtp}
          </button>
        </div>

        {error && (
          <div className="mb-4 p-3 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-900 text-xs text-rose-600 dark:text-rose-400 text-right leading-relaxed">
            {error}
          </div>
        )}

        {otpMessage && (
          <div className="mb-4 p-3 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-900 text-xs text-emerald-700 dark:text-emerald-400 text-right leading-relaxed">
            {otpMessage}
          </div>
        )}

        {/* Password Form */}
        {mode === 'password' ? (
          <form onSubmit={handlePasswordLogin} className="space-y-4">
            <Input
              label={fa.auth.emailOrPhone}
              type="text"
              dir="ltr"
              placeholder={fa.auth.emailOrPhonePlaceholder}
              value={emailOrPhone}
              onChange={(e) => setEmailOrPhone(e.target.value)}
              required
              leadingIcon={<Mail className="h-4 w-4" />}
            />
            <Input
              label={fa.auth.password}
              type="password"
              dir="ltr"
              placeholder={fa.auth.passwordPlaceholder}
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              required
              leadingIcon={<Lock className="h-4 w-4" />}
            />

            <Button
              type="submit"
              isLoading={isLoading}
              className="w-full mt-2"
              size="lg"
            >
              {fa.auth.loginButton}
            </Button>

            {/* Quick Demo Credentials hint */}
            <div className="mt-4 p-3 rounded-xl bg-slate-50 dark:bg-slate-800/40 text-[11px] text-slate-500 text-right leading-relaxed border border-slate-100 dark:border-slate-800">
              <span className="font-bold text-slate-700 dark:text-slate-300">اطلاعات ورود پیش‌فرض:</span>
              <br />
              ایمیل: <span className="font-mono text-blue-600">admin@voicereach.local</span>
              <br />
              رمز عبور: <span className="font-mono text-blue-600">password</span>
            </div>
          </form>
        ) : (
          /* OTP Form */
          <div>
            {!otpSent ? (
              <form onSubmit={handleRequestOtp} className="space-y-4">
                <Input
                  label={fa.auth.phone}
                  type="tel"
                  dir="ltr"
                  placeholder={fa.auth.phonePlaceholder}
                  value={phone}
                  onChange={(e) => setPhone(e.target.value)}
                  required
                  leadingIcon={<Phone className="h-4 w-4" />}
                />
                <Button
                  type="submit"
                  isLoading={isLoading}
                  className="w-full mt-2"
                  size="lg"
                >
                  {fa.auth.requestOtp}
                </Button>
              </form>
            ) : (
              <form onSubmit={handleVerifyOtp} className="space-y-4">
                <Input
                  label={fa.auth.otpCode}
                  type="text"
                  dir="ltr"
                  placeholder={fa.auth.otpPlaceholder}
                  value={otpCode}
                  onChange={(e) => setOtpCode(e.target.value)}
                  required
                  maxLength={5}
                  leadingIcon={<KeyRound className="h-4 w-4" />}
                />
                <Button
                  type="submit"
                  isLoading={isLoading}
                  className="w-full mt-2"
                  size="lg"
                >
                  {fa.auth.verify2fa}
                </Button>
                <button
                  type="button"
                  onClick={() => setOtpSent(false)}
                  className="w-full text-center text-xs text-slate-400 hover:text-slate-600 pt-2 flex items-center justify-center gap-1"
                >
                  <ArrowLeft className="h-3.5 w-3.5" />
                  <span>تغییر شماره موبایل</span>
                </button>
              </form>
            )}
          </div>
        )}
      </div>
    </div>
  )
}
