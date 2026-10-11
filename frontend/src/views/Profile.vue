<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import * as usersApi from '@/api/users'
import { isApiSuccess, apiErrorMessage, requestErrorMessage, type ApiError } from '@/types/api'
import type { Book } from '@/types/book'
import { idToString, LOAN_STATUS_LABELS, type LoanRecord } from '@/types/book'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import BookCard from '@/components/BookCard.vue'

const auth = useAuthStore()
const toast = useToast()

type Tab = 'saved' | 'loans' | 'notifications' | 'downloaded' | 'account'
const activeTab = ref<Tab>('saved')

const profileLoading = ref(true)
const email = ref('')
const memberSince = ref('')

const savedBooks = ref<Book[]>([])
const savedLoading = ref(true)
const savedError = ref('')

const downloadedBooks = ref<Book[]>([])
const downloadedLoading = ref(true)
const downloadedLoaded = ref(false)
const downloadedError = ref('')

// Seeded synchronously from the cached login/profile state (not just left
// blank until loadProfile()'s GET resolves) so the field always shows
// something immediately; usernameTouched then stops that GET from clobbering
// an edit the user already started typing before it resolves.
const usernameInput = ref(auth.username ?? '')
const usernameTouched = ref(false)
const usernameSaving = ref(false)
const usernameError = ref('')

const currentPassword = ref('')
const newPassword = ref('')
const confirmPassword = ref('')
const passwordSaving = ref(false)
const passwordError = ref('')
const passwordSuccess = ref('')

const avatarInitial = computed(() => (auth.username || '?').charAt(0).toUpperCase())

async function loadProfile() {
  profileLoading.value = true
  try {
    const response = await usersApi.getProfile()
    const body = response.data
    if (isApiSuccess(body)) {
      email.value = body.data.email
      if (!usernameTouched.value) {
        usernameInput.value = body.data.username
      }
      auth.setProfile({
        id: body.data._id,
        email: body.data.email,
        username: body.data.username,
        isAdmin: body.data.isAdmin,
      })
      if (body.data.createdAt) {
        memberSince.value = new Date(body.data.createdAt).toLocaleDateString('en-US', {
          year: 'numeric',
          month: 'long',
          day: 'numeric',
        })
      }
    }
  } finally {
    profileLoading.value = false
  }
}

async function loadSaved() {
  savedLoading.value = true
  savedError.value = ''
  try {
    const response = await usersApi.getSavedBooks()
    const body = response.data
    savedBooks.value = isApiSuccess(body) ? body.data : []
  } catch {
    savedError.value = 'There was a problem loading your saved books.'
  } finally {
    savedLoading.value = false
  }
}

async function loadDownloaded() {
  if (downloadedLoaded.value) return
  downloadedLoading.value = true
  downloadedError.value = ''
  try {
    const response = await usersApi.getDownloadedBooks()
    const body = response.data
    downloadedBooks.value = isApiSuccess(body) ? body.data : []
    downloadedLoaded.value = true
  } catch {
    downloadedError.value = 'There was a problem loading your downloaded books.'
  } finally {
    downloadedLoading.value = false
  }
}

const loans = ref<LoanRecord[] | null>(null)
const notifications = ref<usersApi.UserNotification[]>([])
const unread = computed(() => notifications.value.filter((n) => !n.read).length)
const shortDate = (iso: string | null) => (iso ? new Date(iso).toLocaleDateString() : '')

async function loadLoans() {
  try {
    const { data: body } = await usersApi.getMyLoans()
    loans.value = isApiSuccess(body) ? body.data : []
  } catch {
    loans.value = []
  }
}

async function loadNotifications() {
  try {
    const { data: body } = await usersApi.getNotifications()
    if (isApiSuccess(body)) notifications.value = body.data
  } catch {
    // The badge is a convenience; the tab retries when opened.
  }
}

function selectTab(tab: Tab) {
  activeTab.value = tab
  if (tab === 'downloaded') {
    loadDownloaded()
  } else if (tab === 'loans') {
    loadLoans()
  } else if (tab === 'notifications') {
    loadNotifications().then(() => {
      if (unread.value) usersApi.markNotificationsRead().catch(() => {})
    })
  }
}

async function removeSavedBook(book: Book) {
  const id = idToString(book._id)
  try {
    const response = await usersApi.removeBook(id)
    const body = response.data
    if (isApiSuccess(body)) {
      savedBooks.value = savedBooks.value.filter((b) => idToString(b._id) !== id)
      toast.success('Removed from saved books')
    } else {
      toast.error(apiErrorMessage(body as ApiError, 'Failed to remove book'))
    }
  } catch {
    toast.error('An error occurred while removing the book.')
  }
}

async function saveUsername() {
  usernameError.value = ''
  const trimmed = usernameInput.value.trim()
  if (trimmed.length < 3) {
    usernameError.value = 'Username must be at least 3 characters'
    return
  }
  usernameSaving.value = true
  try {
    const response = await usersApi.updateProfile(trimmed)
    const body = response.data
    if (isApiSuccess(body)) {
      auth.setProfile({
        id: auth.profile?.id ?? '',
        email: email.value,
        username: trimmed,
        isAdmin: auth.isAdmin,
      })
      toast.success('Username updated successfully')
    } else {
      usernameError.value = apiErrorMessage(body as ApiError, 'Failed to update username')
    }
  } catch (err) {
    usernameError.value = requestErrorMessage(err, 'An error occurred. Please try again.')
  } finally {
    usernameSaving.value = false
  }
}

async function submitPasswordChange() {
  passwordError.value = ''
  passwordSuccess.value = ''

  if (newPassword.value !== confirmPassword.value) {
    passwordError.value = 'New password and confirmation do not match.'
    return
  }
  if (newPassword.value.length < 8) {
    passwordError.value = 'New password must be at least 8 characters.'
    return
  }

  passwordSaving.value = true
  try {
    const response = await usersApi.changePassword(currentPassword.value, newPassword.value)
    const body = response.data
    if (isApiSuccess(body)) {
      passwordSuccess.value = 'Password updated successfully.'
      toast.success('Password updated successfully')
      currentPassword.value = ''
      newPassword.value = ''
      confirmPassword.value = ''
    } else {
      passwordError.value = apiErrorMessage(body as ApiError, 'Could not update password.')
    }
  } catch (err) {
    passwordError.value = requestErrorMessage(err, 'Could not update password. Please try again.')
  } finally {
    passwordSaving.value = false
  }
}

onMounted(() => {
  loadProfile()
  loadSaved()
  loadNotifications()
})
</script>

<template>
  <div class="container profile-page" data-testid="profile-page">
    <div class="card profile-header">
      <div class="avatar-lg">{{ avatarInitial }}</div>
      <div>
        <h1 data-testid="profile-username-heading">{{ auth.username || 'User' }}</h1>
        <p class="text-muted" data-testid="profile-email">{{ profileLoading ? 'Loading…' : email }}</p>
        <p v-if="memberSince" class="text-muted small" data-testid="profile-member-since">Member since {{ memberSince }}</p>
      </div>
    </div>

    <div class="tabs">
      <button
        type="button"
        :class="{ active: activeTab === 'saved' }"
        data-testid="profile-tab-saved"
        @click="selectTab('saved')"
      >
        Saved Books
      </button>
      <button
        type="button"
        :class="{ active: activeTab === 'loans' }"
        data-testid="profile-tab-loans"
        @click="selectTab('loans')"
      >
        Loans
      </button>
      <button
        type="button"
        :class="{ active: activeTab === 'notifications' }"
        data-testid="profile-tab-notifications"
        @click="selectTab('notifications')"
      >
        Notifications<span v-if="unread" class="unread-badge" :aria-label="`${unread} unread`">{{ unread }}</span>
      </button>
      <button
        type="button"
        :class="{ active: activeTab === 'downloaded' }"
        data-testid="profile-tab-downloaded"
        @click="selectTab('downloaded')"
      >
        Downloaded
      </button>
      <button
        type="button"
        :class="{ active: activeTab === 'account' }"
        data-testid="profile-tab-account"
        @click="selectTab('account')"
      >
        Account
      </button>
    </div>

    <section v-if="activeTab === 'saved'" class="tab-panel">
      <div v-if="savedLoading" class="spinner" role="status" aria-label="Loading saved books"></div>
      <p v-else-if="savedError" class="text-muted">{{ savedError }}</p>
      <div v-else-if="!savedBooks.length" class="empty-state">
        <p>You haven't saved any books to your list yet.</p>
        <RouterLink to="/browse" class="btn btn-primary">Browse Books</RouterLink>
      </div>
      <div v-else class="grid-books">
        <BookCard v-for="book in savedBooks" :key="String(book._id)" :book="book" removable @remove="removeSavedBook(book)" />
      </div>
    </section>

    <section v-else-if="activeTab === 'loans'" class="tab-panel">
      <div v-if="loans === null" class="spinner" role="status" aria-label="Loading loans"></div>
      <div v-else-if="!loans.length" class="empty-state">
        <p>Books you borrow or reserve will appear here.</p>
        <RouterLink to="/browse" class="btn btn-primary">Browse Books</RouterLink>
      </div>
      <ul v-else class="loan-list" data-testid="my-loans">
        <li v-for="l in loans" :key="l.id" class="card loan-item">
          <div>
            <RouterLink v-if="l.book_title" :to="`/books/${l.book_id}`">{{ l.book_title }}</RouterLink>
            <span v-else class="text-muted">Deleted book</span>
            <span class="text-muted"> · {{ l.kind === 'physical' ? 'Physical' : 'PDF' }}</span>
          </div>
          <div class="text-muted small">
            <template v-if="l.status === 'reserved'">Pick up at the library desk by {{ shortDate(l.pickup_by) }}</template>
            <template v-else-if="l.status === 'out' || l.status === 'active'">Due {{ shortDate(l.due_at) }}</template>
            <template v-else-if="l.status === 'overdue'">Was due {{ shortDate(l.due_at) }}: please return it</template>
            <template v-else-if="l.returned_at">Returned {{ shortDate(l.returned_at) }}</template>
            <template v-else>{{ shortDate(l.borrowed_at ?? l.reserved_at) }}</template>
          </div>
          <span class="loan-status" :class="`loan-status-${l.status}`">{{ LOAN_STATUS_LABELS[l.status] }}</span>
        </li>
      </ul>
    </section>

    <section v-else-if="activeTab === 'notifications'" class="tab-panel">
      <div v-if="!notifications.length" class="empty-state">
        <p>No notifications yet. You'll hear here (and by email) when you check out, return, or are late with a book.</p>
      </div>
      <ul v-else class="loan-list" data-testid="notifications">
        <li v-for="n in notifications" :key="n.id" class="card loan-item" :class="{ unread: !n.read }">
          <div>
            <RouterLink v-if="n.book_id" :to="`/books/${n.book_id}`">{{ n.message }}</RouterLink>
            <template v-else>{{ n.message }}</template>
          </div>
          <div class="text-muted small">{{ n.created_at ? new Date(n.created_at).toLocaleString() : '' }}</div>
        </li>
      </ul>
    </section>

    <section v-else-if="activeTab === 'downloaded'" class="tab-panel">
      <div v-if="downloadedLoading" class="spinner" role="status" aria-label="Loading downloaded books"></div>
      <p v-else-if="downloadedError" class="text-muted">{{ downloadedError }}</p>
      <div v-else-if="!downloadedBooks.length" class="empty-state">
        <p>Books you download will appear here.</p>
        <RouterLink to="/browse" class="btn btn-primary">Browse Books</RouterLink>
      </div>
      <div v-else class="grid-books">
        <BookCard v-for="book in downloadedBooks" :key="String(book._id)" :book="book" />
      </div>
    </section>

    <section v-else class="tab-panel account-panel">
      <div class="card account-card">
        <h3>Edit username</h3>
        <p v-if="usernameError" class="alert alert-danger" data-testid="profile-username-error">{{ usernameError }}</p>
        <div class="form-field">
          <label for="profile-username">Username</label>
          <input
            id="profile-username"
            v-model="usernameInput"
            type="text"
            minlength="3"
            required
            data-testid="profile-username-input"
            @input="usernameTouched = true"
          />
        </div>
        <button
          type="button"
          class="btn btn-primary"
          :disabled="usernameSaving"
          data-testid="profile-save-username"
          @click="saveUsername"
        >
          {{ usernameSaving ? 'Saving…' : 'Save' }}
        </button>
      </div>

      <div class="card account-card">
        <h3>Change password</h3>
        <p v-if="passwordError" class="alert alert-danger" data-testid="profile-password-feedback">{{ passwordError }}</p>
        <p v-if="passwordSuccess" class="alert alert-success" data-testid="profile-password-feedback">{{ passwordSuccess }}</p>
        <form @submit.prevent="submitPasswordChange">
          <div class="form-field">
            <label for="current-password">Current password</label>
            <input
              id="current-password"
              v-model="currentPassword"
              type="password"
              autocomplete="current-password"
              required
              data-testid="profile-current-password"
            />
          </div>
          <div class="form-field">
            <label for="new-password">New password</label>
            <input
              id="new-password"
              v-model="newPassword"
              type="password"
              minlength="8"
              autocomplete="new-password"
              required
              data-testid="profile-new-password"
            />
          </div>
          <div class="form-field">
            <label for="confirm-new-password">Confirm new password</label>
            <input
              id="confirm-new-password"
              v-model="confirmPassword"
              type="password"
              minlength="8"
              autocomplete="new-password"
              required
              data-testid="profile-confirm-new-password"
            />
          </div>
          <button type="submit" class="btn btn-primary" :disabled="passwordSaving" data-testid="profile-update-password">
            {{ passwordSaving ? 'Updating…' : 'Update password' }}
          </button>
        </form>
      </div>
    </section>
  </div>
</template>

<style scoped src="@/css/views/Profile.css"></style>
