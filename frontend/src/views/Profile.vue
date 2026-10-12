<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import * as usersApi from '@/api/users'
import * as booksApi from '@/api/books'
import * as friendsApi from '@/api/friends'
import { isApiSuccess, apiErrorMessage, requestErrorMessage, type ApiError } from '@/types/api'
import type { Book } from '@/types/book'
import { idToString, type LoanRecord } from '@/types/book'
import LoanStatus from '@/components/LoanStatus.vue'
import { shortDate, shortDateTime } from '@/utils/date'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import BookCard from '@/components/BookCard.vue'

const auth = useAuthStore()
const route = useRoute()
const toast = useToast()

const tabs = ['saved', 'loans', 'friends', 'notifications', 'downloaded', 'account'] as const
type Tab = (typeof tabs)[number]
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
        memberSince.value = shortDate(body.data.createdAt)
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

async function loadLoans() {
  try {
    const { data: body } = await usersApi.getMyLoans()
    loans.value = isApiSuccess(body) ? body.data : []
  } catch {
    loans.value = []
  }
}

async function renewLoan(l: LoanRecord) {
  try {
    const { data: body } = await booksApi.renewBook(l.book_id)
    if (isApiSuccess(body)) {
      toast.success(`Renewed until ${shortDate(body.data.due_at)}`)
      await loadLoans()
    } else {
      toast.error(apiErrorMessage(body as ApiError, 'Could not renew'))
    }
  } catch (err) {
    toast.error(requestErrorMessage(err, 'Could not renew'))
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

const friends = ref<friendsApi.Friend[] | null>(null)
const incoming = computed(() => (friends.value ?? []).filter((f) => f.status === 'incoming'))
const sharing = ref<friendsApi.Sharing | null>(null)
const sharingOptions: { key: keyof friendsApi.Sharing; label: string }[] = [
  { key: 'saved', label: 'Saved books' },
  { key: 'reviews', label: 'Reviews' },
  { key: 'loans', label: "Books I'm borrowing now" },
  { key: 'downloads', label: 'Downloaded books' },
  { key: 'notes', label: 'Notes and highlights, when they read the same book' },
]
// Requests that need an answer first, then friends, then requests still waiting on the other reader.
const friendGroups = computed(() =>
  (
    [
      { status: 'incoming', title: 'Requests' },
      { status: 'friends', title: 'Friends' },
      { status: 'outgoing', title: 'Sent requests' },
    ] as const
  ).map((g) => ({ ...g, items: (friends.value ?? []).filter((f) => f.status === g.status) })),
)
const friendEmail = ref('')
const friendSending = ref(false)

async function loadFriends() {
  try {
    const { data: body } = await friendsApi.getFriends()
    friends.value = isApiSuccess(body) ? body.data : []
  } catch {
    friends.value = []
  }
}

async function loadSharing() {
  try {
    const { data: body } = await friendsApi.getSharing()
    if (isApiSuccess(body)) sharing.value = body.data
  } catch {
    // The switches stay hidden; the tab retries when reopened.
  }
}

async function toggleSharing(key: keyof friendsApi.Sharing, value: boolean) {
  try {
    const { data: body } = await friendsApi.setSharing({ [key]: value })
    if (isApiSuccess(body)) {
      sharing.value = body.data
      return
    }
    toast.error(apiErrorMessage(body as ApiError, 'Could not save'))
  } catch (err) {
    toast.error(requestErrorMessage(err, 'Could not save'))
  }
  await loadSharing() // put the checkbox back to what's stored
}

async function sendFriendRequest() {
  if (!friendEmail.value.trim()) return
  friendSending.value = true
  try {
    const { data: body } = await friendsApi.sendRequest(friendEmail.value.trim())
    if (isApiSuccess(body)) {
      toast.success(body.data)
      friendEmail.value = ''
      await loadFriends()
    } else {
      toast.error(apiErrorMessage(body as ApiError, 'Could not send the request'))
    }
  } catch (err) {
    toast.error(requestErrorMessage(err, 'Could not send the request'))
  } finally {
    friendSending.value = false
  }
}

async function answerFriend(f: friendsApi.Friend, accept: boolean) {
  if (f.status === 'friends' && !confirm(`Remove ${f.username} from your friends? You'll stop seeing what each other shares.`)) return
  try {
    const { data: body } = accept ? await friendsApi.acceptRequest(f.user_id) : await friendsApi.removeFriend(f.user_id)
    if (!isApiSuccess(body)) toast.error(apiErrorMessage(body as ApiError, 'Something went wrong'))
  } catch (err) {
    toast.error(requestErrorMessage(err, 'Something went wrong'))
  }
  await loadFriends()
}

function selectTab(tab: Tab) {
  activeTab.value = tab
  if (tab === 'downloaded') {
    loadDownloaded()
  } else if (tab === 'loans') {
    loadLoans()
  } else if (tab === 'friends') {
    loadFriends()
    loadSharing()
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
  // /profile?tab=friends — where the friend page's back link lands. Opening the Friends tab loads friends and
  // sharing itself; otherwise load them now, for the requests badge and so the tab opens complete.
  const tab = typeof route.query.tab === 'string' && tabs.includes(route.query.tab as Tab) ? (route.query.tab as Tab) : null
  if (tab) selectTab(tab)
  if (tab !== 'friends') {
    loadFriends()
    loadSharing()
  }
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
        :class="{ active: activeTab === 'friends' }"
        data-testid="profile-tab-friends"
        @click="selectTab('friends')"
      >
        Friends<span v-if="incoming.length" class="unread-badge" :aria-label="`${incoming.length} requests`">{{ incoming.length }}</span>
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
          <div class="loan-main">
            <RouterLink v-if="l.book_title" :to="`/books/${l.book_id}`" class="loan-title">{{ l.book_title }}</RouterLink>
            <span v-else class="loan-title text-muted">Deleted book</span>
            <span class="loan-when">
              <template v-if="l.status === 'reserved'">
                {{ l.kind === 'physical' ? 'Pick it up at the library desk' : 'Start reading' }} by {{ shortDate(l.pickup_by) }}
              </template>
              <template v-else-if="l.status === 'out' || l.status === 'active'">Due {{ shortDate(l.due_at) }}</template>
              <template v-else-if="l.status === 'overdue'">Was due {{ shortDate(l.due_at) }}. Please return it.</template>
              <template v-else-if="l.returned_at">Returned {{ shortDate(l.returned_at) }}</template>
              <template v-else>{{ shortDate(l.borrowed_at ?? l.reserved_at) }}</template>
              · {{ l.kind === 'physical' ? 'Physical book' : 'PDF' }}
            </span>
          </div>
          <div class="loan-side">
            <button
              v-if="(l.status === 'out' || l.status === 'active') && l.renewals < 2"
              type="button"
              class="btn btn-outline btn-sm"
              @click="renewLoan(l)"
            >
              Renew
            </button>
            <LoanStatus :status="l.status" />
          </div>
        </li>
      </ul>
    </section>

    <section v-else-if="activeTab === 'friends'" class="tab-panel friends-panel">
      <div class="friends-main">
        <div v-if="friends === null" class="spinner" role="status" aria-label="Loading friends"></div>
        <div v-else-if="!friends.length" class="empty-state">
          <p>No friends yet. Add someone by their email to see what they're reading.</p>
        </div>
        <template v-else>
          <template v-for="group in friendGroups" :key="group.status">
            <h3 v-if="group.items.length" class="friends-heading">{{ group.title }}</h3>
            <ul v-if="group.items.length" class="loan-list" data-testid="friends">
              <li v-for="f in group.items" :key="f.user_id" class="card loan-item" :class="{ unread: f.status === 'incoming' }">
                <div class="loan-main">
                  <RouterLink v-if="f.status === 'friends'" :to="`/friends/${f.user_id}`" class="loan-title">{{ f.username }}</RouterLink>
                  <span v-else class="loan-title">{{ f.username }}</span>
                  <span class="loan-when">
                    <template v-if="f.status === 'friends'">Friends since {{ shortDate(f.since) }}</template>
                    <template v-else-if="f.status === 'incoming'">Wants to be your friend</template>
                    <template v-else>Sent {{ shortDate(f.since) }}</template>
                  </span>
                </div>
                <div class="loan-side">
                  <button v-if="f.status === 'incoming'" type="button" class="btn btn-primary btn-sm" @click="answerFriend(f, true)">
                    Accept
                  </button>
                  <button type="button" class="btn btn-outline btn-sm" @click="answerFriend(f, false)">
                    {{ f.status === 'friends' ? 'Remove' : f.status === 'incoming' ? 'Decline' : 'Cancel request' }}
                  </button>
                </div>
              </li>
            </ul>
          </template>
        </template>
      </div>

      <aside class="friends-aside">
        <form class="card account-card" data-testid="friend-request-form" @submit.prevent="sendFriendRequest">
          <h3>Add a friend</h3>
          <p class="text-muted small">Once they accept, you can each see what the other shares.</p>
          <div class="form-field">
            <label for="friend-email">Their email</label>
            <input id="friend-email" v-model="friendEmail" type="email" required autocomplete="off" data-testid="friend-email-input" />
          </div>
          <button type="submit" class="btn btn-primary" :disabled="friendSending">
            {{ friendSending ? 'Sending…' : 'Send request' }}
          </button>
        </form>

        <div
          v-if="sharing"
          class="card account-card"
          role="group"
          aria-labelledby="sharing-heading"
          data-testid="sharing-settings"
        >
          <h3 id="sharing-heading">What your friends can see</h3>
          <label v-for="o in sharingOptions" :key="o.key" class="sharing-option">
            <input
              type="checkbox"
              :checked="sharing[o.key]"
              :data-testid="`sharing-${o.key}`"
              @change="toggleSharing(o.key, ($event.target as HTMLInputElement).checked)"
            />
            {{ o.label }}
          </label>
          <p class="text-muted small sharing-hint">Changes save as you tick them.</p>
        </div>
      </aside>
    </section>

    <section v-else-if="activeTab === 'notifications'" class="tab-panel">
      <div v-if="!notifications.length" class="empty-state">
        <p>No notifications yet. You'll hear here (and by email) when you check out, return, or are late with a book.</p>
      </div>
      <ul v-else class="loan-list" data-testid="notifications">
        <li v-for="n in notifications" :key="n.id" class="card loan-item" :class="{ unread: !n.read }">
          <div class="loan-main">
            <span>{{ n.message }}</span>
            <span class="loan-when">{{ shortDateTime(n.created_at) }}</span>
          </div>
          <div class="loan-side">
            <RouterLink v-if="n.book_id" :to="`/books/${n.book_id}`" class="link-btn">Open book</RouterLink>
          </div>
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
