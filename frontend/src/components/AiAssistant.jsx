import { useEffect, useRef, useState } from 'react'
import { chat, confirmAction, changePassword } from '../api/ai.js'
import { getAppUrl } from '../api/auth.js'

const welcome = {
  role: 'assistant',
  content: 'Xin chào! Tôi là trợ lý ghi chú Notezy. Bạn muốn tìm, tóm tắt hay tạo ghi chú?',
}

export default function AiAssistant({ currentNoteId = 0, page = 'notes_list' }) {
  const [open, setOpen] = useState(false)
  const [input, setInput] = useState('')
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState('')
  const [conversationId, setConversationId] = useState(null)
  const [messages, setMessages] = useState([welcome])
  const listRef = useRef(null)
  const passwordDialog = useRef(null)
  const [passwordToken, setPasswordToken] = useState(null)
  const [passwordError, setPasswordError] = useState('')
  const [passwordSaving, setPasswordSaving] = useState(false)
  useEffect(() => {
    if (passwordToken && passwordDialog.current && !passwordDialog.current.open) passwordDialog.current.showModal()
  }, [passwordToken])
  async function savePassword(event) {
    event.preventDefault()
    const form = event.currentTarget
    setPasswordSaving(true)
    setPasswordError('')
    try {
      const fields = Object.fromEntries(new FormData(form))
      if (fields.new_password !== fields.confirm_password) throw new Error('Hai lần nhập mật khẩu mới chưa khớp.')
      const result = await changePassword({...fields, form_token:passwordToken})
      form.reset()
      passwordDialog.current.close()
      setPasswordToken(null)
      setMessages(prev => [...prev, {role:'assistant', content:result.message}])
    } catch (err) { setPasswordError(err.message) }
    finally { setPasswordSaving(false) }
  }

  useEffect(() => {
    if (listRef.current) {
      listRef.current.scrollTop = listRef.current.scrollHeight
    }
  }, [messages, loading, open])

  async function send(text) {
    const message = (text || '').trim()
    if (!message || loading) return
    setError('')
    setMessages((prev) => [...prev, { role: 'user', content: message }])
    setLoading(true)
    try {
      const data = await chat(message, conversationId, {
        page,
        current_note_id: currentNoteId,
      })
      if (data.conversation_id) setConversationId(data.conversation_id)
      if (data.response?.type === 'password_change_form') {
        setPasswordError('')
        setPasswordToken(data.response.form_token)
      }
      setMessages((prev) => [
        ...prev,
        { role: 'assistant', content: data.response?.content || '', response: data.response },
      ])
    } catch (err) {
      setError(err.message)
      setMessages((prev) => [...prev, { role: 'assistant', content: err.message, error: true }])
    } finally {
      setLoading(false)
    }
  }

  async function onConfirm(msg, edits) {
    if (!msg.response?.confirmation_token) return
    setLoading(true)
    setError('')
    try {
      const data = await confirmAction(msg.response.confirmation_token, edits)
      setMessages((prev) => [
        ...prev,
        { role: 'assistant', content: data.response?.content || '', response: data.response },
      ])
    } catch (err) {
      setError(err.message)
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="fixed bottom-6 right-6 z-50">
      <dialog ref={passwordDialog} className="rounded-2xl p-6 w-[min(94vw,440px)] backdrop:bg-black/50" onClose={() => {passwordDialog.current.querySelector('form').reset(); setPasswordToken(null)}}>
        <form onSubmit={savePassword} className="grid gap-3">
          <h3 className="text-xl font-bold">Đổi mật khẩu tài khoản</h3>
          <p className="text-sm">Thông tin này gửi trực tiếp tới Notezy, không gửi vào mô hình AI.</p>
          <label>Mật khẩu hiện tại<input className="border rounded p-2 w-full" name="current_password" type="password" autoComplete="current-password" required /></label>
          <label>Mật khẩu mới<input className="border rounded p-2 w-full" name="new_password" type="password" autoComplete="new-password" minLength={6} maxLength={72} required /></label>
          <label>Xác nhận mật khẩu mới<input className="border rounded p-2 w-full" name="confirm_password" type="password" autoComplete="new-password" minLength={6} maxLength={72} required /></label>
          <p role="status" className="text-red-600">{passwordError}</p>
          <button className="bg-purple-700 text-white rounded p-2" disabled={passwordSaving}>Lưu mật khẩu</button>
          <button type="button" onClick={() => passwordDialog.current.close()}>Hủy</button>
        </form>
      </dialog>
      {open && (
        <div className="notezy-react-ai-panel bg-white w-[min(420px,calc(100vw-24px))] h-[min(640px,70vh)] rounded-2xl shadow-2xl border border-gray-200 mb-4 overflow-hidden flex flex-col dark:bg-neutral-950 dark:border-white/10">
          <div className="notezy-react-ai-header text-white p-3 font-semibold flex justify-between items-center">
            <span className="flex items-center gap-2"><img src="/assets/notezy-mascot-2026.png" alt="" width="37" height="37" /> Trợ lý Notezy</span>
            <button type="button" onClick={() => setOpen(false)} className="text-white" aria-label="Đóng trợ lý thông minh">✕</button>
          </div>
          <div ref={listRef} className="flex-1 p-3 overflow-y-auto bg-gray-50 flex flex-col gap-3 dark:bg-black">
            {messages.map((msg, idx) => (
              <Message
                key={idx}
                msg={msg}
                onConfirm={onConfirm}
                onPasswordOpen={token => {setPasswordError(''); setPasswordToken(token)} }
                onCancel={() =>
                  setMessages((prev) => [...prev, { role: 'assistant', content: 'Đã hủy thao tác.' }])
                }
              />
            ))}
            {loading && <div className="text-sm text-gray-500 dark:text-gray-400">Đang suy nghĩ...</div>}
            {error && (
              <button className="text-sm text-red-600 underline self-start dark:text-red-400" onClick={() => send(messages.filter(m => m.role === 'user').at(-1)?.content)}>
                Thử lại
              </button>
            )}
          </div>
          <form
            className="p-3 bg-white border-t border-gray-200 flex gap-2 dark:bg-neutral-950 dark:border-white/10"
            onSubmit={(e) => {
              e.preventDefault()
              const v = input
              setInput('')
              send(v)
            }}
          >
            <input
              className="flex-1 border border-gray-300 rounded-full px-4 py-2 text-sm dark:border-white/15 dark:bg-neutral-900 dark:text-white dark:placeholder:text-gray-500"
              placeholder="Nhắn với trợ lý..."
              value={input}
              onChange={(e) => setInput(e.target.value)}
            />
            <button className="notezy-react-ai-send px-4 py-2 text-white rounded-full text-sm transition-colors" disabled={loading}>
              Gửi
            </button>
          </form>
        </div>
      )}
      <button
        onClick={() => setOpen((v) => !v)}
        className="notezy-react-ai-fab"
        aria-label={open ? 'Đóng Trợ lý Notezy' : 'Mở Trợ lý Notezy'}
        aria-expanded={open}
      >
        <img src="/assets/notezy-mascot-2026.png" alt="" width="56" height="56" />
      </button>
    </div>
  )
}

function Message({ msg, onConfirm, onCancel, onPasswordOpen }) {
  if (msg.response?.type === 'password_change_form') return <button type="button" className="border rounded p-3" onClick={() => onPasswordOpen(msg.response.form_token)}>Mở biểu mẫu đổi mật khẩu</button>
  const resp = msg.response
  const [title, setTitle] = useState(resp?.title || '')
  const [content, setContent] = useState(resp?.content || '')

  if (resp?.type === 'note_created') {
    // Note was already auto-created server-side (no confirm step for create_note).
    return (
      <div
        className="border rounded-xl p-3 text-sm"
        style={{ background: resp.background_color || '#ffffff', color: resp.text_color || '#000000' }}
      >
        <div className="font-semibold mb-1">✅ Đã tạo ghi chú</div>
        <div className="font-semibold">{resp.title}</div>
        <div className="whitespace-pre-wrap text-sm mt-1">{resp.note_content}</div>
        <div className="flex flex-wrap gap-2 my-2">
          {(resp.labels || []).map((l) => (
            <span key={l} className="text-xs bg-black/10 px-2 py-1 rounded-full">{l}</span>
          ))}
        </div>
        <a className="text-xs underline text-[#625a7e]" href={getAppUrl(`edit_note.php?id=${encodeURIComponent(resp.note_id)}`)}>Mở ghi chú</a>
      </div>
    )
  }

  if (resp?.type === 'note_preview') {
    return (
      <div className="bg-white border rounded-xl p-3 text-sm dark:bg-neutral-950 dark:border-white/10 dark:text-white">
        <div className="font-semibold mb-2">Xem trước ghi chú</div>
        <input className="w-full border rounded p-2 mb-2 dark:border-white/15 dark:bg-neutral-900 dark:text-white" value={title} onChange={(e) => setTitle(e.target.value)} />
        <textarea className="w-full border rounded p-2 min-h-[120px] dark:border-white/15 dark:bg-neutral-900 dark:text-white" value={content} onChange={(e) => setContent(e.target.value)} />
        <div className="flex flex-wrap gap-2 my-2">
          {(resp.labels || []).map((l) => (
            <span key={l} className="text-xs bg-gray-100 px-2 py-1 rounded-full dark:bg-white/10 dark:text-gray-200">{l}</span>
          ))}
        </div>
        <div className="flex gap-2">
          <button className="bg-green-600 text-white px-3 py-1 rounded transition-colors hover:bg-green-700 dark:bg-red-600 dark:hover:bg-red-500" onClick={() => onConfirm(msg, { title, content, labels: resp.labels })}>
            {resp.action === 'update_note' ? 'Cập nhật' : 'Tạo'}
          </button>
          <button className="border px-3 py-1 rounded dark:border-white/20 dark:text-gray-200" onClick={onCancel}>Hủy</button>
        </div>
      </div>
    )
  }

  if (resp?.type === 'action_confirmation') {
    return (
      <div className="bg-white border rounded-xl p-3 text-sm dark:bg-neutral-950 dark:border-white/10 dark:text-white">
        <div className="font-semibold mb-2">{resp.action === 'delete_note' ? 'Xóa ghi chú?' : 'Xác nhận'}</div>
        <p className="mb-3">{resp.content}</p>
        <div className="flex gap-2">
          <button className="bg-red-600 text-white px-3 py-1 rounded transition-colors hover:bg-red-700 dark:hover:bg-red-500" onClick={() => onConfirm(msg, {})}>
            {resp.action === 'delete_note' ? 'Xóa' : 'Xác nhận'}
          </button>
          <button className="border px-3 py-1 rounded dark:border-white/20 dark:text-gray-200" onClick={onCancel}>Hủy</button>
        </div>
      </div>
    )
  }

  return (
    <div className={`p-2 rounded-lg max-w-[85%] text-sm ${msg.role === 'assistant' ? 'bg-white border self-start dark:bg-neutral-900 dark:border-white/10 dark:text-white' : 'bg-green-600 text-white self-end dark:bg-red-600'}`}>
      {msg.content}
    </div>
  )
}
