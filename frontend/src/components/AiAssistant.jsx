import { useEffect, useRef, useState } from 'react'
import { chat, confirmAction } from '../api/ai.js'

const welcome = {
  role: 'assistant',
  content: 'Xin chào! Tôi là trợ lý ghi chú Notezy. Bạn muốn tìm, tóm tắt hay tạo note?',
}

export default function AiAssistant({ currentNoteId = 0, page = 'notes_list' }) {
  const [open, setOpen] = useState(false)
  const [input, setInput] = useState('')
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState('')
  const [conversationId, setConversationId] = useState(null)
  const [messages, setMessages] = useState([welcome])
  const listRef = useRef(null)

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
      setConversationId(data.conversation_id)
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
      {open && (
        <div className="bg-white w-[min(420px,calc(100vw-24px))] h-[min(640px,70vh)] rounded-2xl shadow-2xl border border-gray-200 mb-4 overflow-hidden flex flex-col dark:bg-neutral-950 dark:border-white/10">
          <div className="bg-green-600 text-white p-3 font-semibold flex justify-between items-center dark:bg-red-600">
            <span>Notezy AI</span>
            <button onClick={() => setOpen(false)} className="text-white">✕</button>
          </div>
          <div ref={listRef} className="flex-1 p-3 overflow-y-auto bg-gray-50 flex flex-col gap-3 dark:bg-black">
            {messages.map((msg, idx) => (
              <Message
                key={idx}
                msg={msg}
                onConfirm={onConfirm}
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
              placeholder="Nhắn với AI..."
              value={input}
              onChange={(e) => setInput(e.target.value)}
            />
            <button className="px-4 py-2 bg-green-600 text-white rounded-full text-sm transition-colors hover:bg-green-700 dark:bg-red-600 dark:hover:bg-red-500" disabled={loading}>
              Gửi
            </button>
          </form>
        </div>
      )}
      <button
        onClick={() => setOpen((v) => !v)}
        className="w-14 h-14 bg-green-600 text-white rounded-full shadow-lg text-2xl transition-colors hover:bg-green-700 dark:bg-red-600 dark:hover:bg-red-500"
        aria-label="Open Notezy AI"
      >
        ✦
      </button>
    </div>
  )
}

function Message({ msg, onConfirm, onCancel }) {
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
        <a className="text-xs underline text-green-700 dark:text-red-400" href={`/edit_note.php?id=${resp.note_id}`}>Mở ghi chú</a>
      </div>
    )
  }

  if (resp?.type === 'note_preview') {
    return (
      <div className="bg-white border rounded-xl p-3 text-sm dark:bg-neutral-950 dark:border-white/10 dark:text-white">
        <div className="font-semibold mb-2">Note Preview</div>
        <input className="w-full border rounded p-2 mb-2 dark:border-white/15 dark:bg-neutral-900 dark:text-white" value={title} onChange={(e) => setTitle(e.target.value)} />
        <textarea className="w-full border rounded p-2 min-h-[120px] dark:border-white/15 dark:bg-neutral-900 dark:text-white" value={content} onChange={(e) => setContent(e.target.value)} />
        <div className="flex flex-wrap gap-2 my-2">
          {(resp.labels || []).map((l) => (
            <span key={l} className="text-xs bg-gray-100 px-2 py-1 rounded-full dark:bg-white/10 dark:text-gray-200">{l}</span>
          ))}
        </div>
        <div className="flex gap-2">
          <button className="bg-green-600 text-white px-3 py-1 rounded transition-colors hover:bg-green-700 dark:bg-red-600 dark:hover:bg-red-500" onClick={() => onConfirm(msg, { title, content, labels: resp.labels })}>
            {resp.action === 'update_note' ? 'Update' : 'Create'}
          </button>
          <button className="border px-3 py-1 rounded dark:border-white/20 dark:text-gray-200" onClick={onCancel}>Cancel</button>
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
            {resp.action === 'delete_note' ? 'Delete' : 'Confirm'}
          </button>
          <button className="border px-3 py-1 rounded dark:border-white/20 dark:text-gray-200" onClick={onCancel}>Cancel</button>
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
