<script setup lang="ts">
import { reactive, ref } from 'vue'
import * as adminApi from '@/api/admin'
import { isApiSuccess, requestErrorMessage } from '@/types/api'
import { useToast } from '@/composables/useToast'
import { fillEmpty, useCategoryOptions } from '@/composables/useCategoryOptions'
import CategoryPicker from '@/components/CategoryPicker.vue'

const toast = useToast()
const availableCategories = useCategoryOptions()

interface PendingFile {
  file: File
  title: string
  author: string
  year: string
  isbn: string
  description: string
  categories: string[]
  /** Set once a lookup matched, so the admin knows to check the filled-in details. */
  lookedUp: boolean
  status: 'pending' | 'success' | 'failed'
  reason?: string
}

const pendingFiles = ref<PendingFile[]>([])
const dragging = ref(false)
const uploading = ref(false)
const progress = ref(0)
const feedback = ref('')
const feedbackVariant = ref<'info' | 'success' | 'warning' | 'danger'>('info')

const defaults = reactive({
  categories: [] as string[],
  downloadable: true,
  status: 'draft',
})

function titleFromFilename(name: string): string {
  return name.replace(/\.pdf$/i, '').replace(/[_-]/g, ' ')
}

function addFiles(files: File[]) {
  const pdfFiles = files.filter((f) => f.type === 'application/pdf' || /\.pdf$/i.test(f.name))
  if (!pdfFiles.length) {
    toast.error('Please select supported PDF files only.')
    return
  }
  pdfFiles.forEach((file) => {
    pendingFiles.value.push({
      file,
      title: titleFromFilename(file.name),
      author: '',
      year: '',
      isbn: '',
      description: '',
      categories: [],
      lookedUp: false,
      status: 'pending',
    })
    // Push first, then fill in the background (the reactive entry, not the raw object).
    prefill(pendingFiles.value[pendingFiles.value.length - 1])
  })
}

/**
 * Best effort, never overwriting what the admin typed: the PDF's own Title/Author and printed ISBN, then
 * Google Books / Open Library (by ISBN, else title/author) for the description, year, categories and anything missing.
 */
async function prefill(entry: PendingFile) {
  const guess = entry.title
  const untouched = () => entry.title === guess
  try {
    const { readPdfDetails } = await import('@/utils/pdfjs')
    const pdf = await readPdfDetails(entry.file)
    if (pdf.title && untouched()) entry.title = pdf.title
    entry.author ||= pdf.author
    entry.isbn ||= pdf.isbn
  } catch {
    // Unreadable/encrypted PDF: keep the filename guess; the server validates the file on upload.
  }

  // Only a real title (from the PDF or the admin) is worth a title search; a filename guess finds wrong books.
  const realTitle = entry.title !== titleFromFilename(entry.file.name)
  if (!entry.isbn && !realTitle) return
  const query = { isbn: entry.isbn, title: realTitle ? entry.title : '', author: entry.author }
  try {
    const { data: body } = await adminApi.lookupMetadata(query)
    const found = isApiSuccess(body) ? body.data : null
    if (!found) return
    if (found.title && untouched()) entry.title = found.title
    fillEmpty(entry, found, availableCategories.value)
    entry.lookedUp = true
  } catch {
    // Lookup is optional; the admin fills the fields by hand.
  }
}

function onFileInput(event: Event) {
  const input = event.target as HTMLInputElement
  if (input.files) addFiles(Array.from(input.files))
  input.value = ''
}

function onDrop(event: DragEvent) {
  dragging.value = false
  const files = event.dataTransfer?.files
  if (files) addFiles(Array.from(files))
}

function removeFile(index: number) {
  pendingFiles.value.splice(index, 1)
}

async function handleUpload() {
  if (!pendingFiles.value.length) {
    toast.error('Please select at least one PDF file to upload.')
    return
  }
  const missingTitle = pendingFiles.value.some((f) => !f.title.trim())
  if (missingTitle) {
    toast.error('Please provide titles for all files.')
    return
  }

  uploading.value = true
  progress.value = 0
  feedbackVariant.value = 'info'
  feedback.value = 'Uploading files…'

  try {
    const response = await adminApi.uploadBooks(
      pendingFiles.value.map((f) => ({
        file: f.file,
        title: f.title.trim(),
        author: f.author.trim(),
        year: f.year.trim(),
        isbn: f.isbn.trim(),
        description: f.description.trim(),
        categories: f.categories,
      })),
      {
        categories: defaults.categories,
        downloadable: defaults.downloadable,
        status: defaults.status,
      },
      (percent) => {
        progress.value = percent
      },
    )

    const body = response.data
    if (isApiSuccess(body)) {
      const results = body.data.results
      results.success.forEach((item) => {
        const entry = pendingFiles.value.find((f) => f.file.name === item.filename)
        if (entry) entry.status = 'success'
      })
      results.failed.forEach((item) => {
        const entry = pendingFiles.value.find((f) => f.file.name === item.filename)
        if (entry) {
          entry.status = 'failed'
          entry.reason = item.reason
        }
      })

      const successCount = results.success.length
      const failedCount = results.failed.length
      if (failedCount === 0) {
        feedbackVariant.value = 'success'
        feedback.value = `All ${successCount} books were uploaded successfully!`
        toast.success(feedback.value)
        setTimeout(() => {
          pendingFiles.value = []
          feedback.value = ''
        }, 2000)
      } else {
        feedbackVariant.value = 'warning'
        feedback.value = `${successCount} succeeded, ${failedCount} failed.`
      }
    } else {
      feedbackVariant.value = 'danger'
      feedback.value = 'Upload failed: some or all files could not be processed.'
    }
  } catch (err) {
    feedbackVariant.value = 'danger'
    feedback.value = `Upload failed: ${requestErrorMessage(err, 'Network error')}`
  } finally {
    uploading.value = false
  }
}
</script>

<template>
  <div class="container upload-pdf-page">
    <header class="page-header">
      <h1>Upload books</h1>
      <RouterLink to="/admin" class="link-btn">Back to books</RouterLink>
    </header>

    <div
      class="drop-area"
      :class="{ dragging }"
      @dragover.prevent="dragging = true"
      @dragleave.prevent="dragging = false"
      @drop.prevent="onDrop"
    >
      <input
        id="pdf-files-input"
        type="file"
        accept="application/pdf"
        multiple
        hidden
        data-testid="file-input"
        @change="onFileInput"
      />
      <p>Drop PDF files here or <label for="pdf-files-input" class="btn btn-outline">Choose files</label></p>
      <p class="drop-hint">Title, author, ISBN and description are filled in from each PDF where possible.</p>
    </div>

    <ul v-if="pendingFiles.length" class="files-list">
      <li
        v-for="(item, index) in pendingFiles"
        :key="item.file.name + index"
        class="file-row"
        :class="item.status"
        :data-testid="`file-row-${index}`"
      >
        <div class="file-row-header">
          <span class="file-name">{{ item.file.name }}</span>
          <button type="button" class="btn-close" :aria-label="`Remove ${item.file.name}`" @click="removeFile(index)">
            &times;
          </button>
        </div>
        <div class="file-fields">
          <label class="field wide">
            <span>Title</span>
            <input v-model="item.title" type="text" required :data-testid="`file-title-input-${index}`" />
          </label>
          <label class="field">
            <span>Author</span>
            <input v-model="item.author" type="text" :data-testid="`file-author-input-${index}`" />
          </label>
          <label class="field">
            <span>Year</span>
            <input v-model="item.year" type="text" inputmode="numeric" />
          </label>
          <label class="field">
            <span>ISBN</span>
            <input v-model="item.isbn" type="text" />
          </label>
          <label class="field full">
            <span>Description</span>
            <textarea v-model="item.description" rows="3"></textarea>
          </label>
          <div class="field full">
            <label :for="`file-categories-${index}`">Categories</label>
            <CategoryPicker
              :id="`file-categories-${index}`"
              v-model="item.categories"
              :options="availableCategories"
              placeholder="Add a category"
            />
          </div>
        </div>
        <p v-if="item.lookedUp" class="file-source">Details filled from Google Books / Open Library. Check they match this book.</p>
        <p v-if="item.reason" class="file-reason">{{ item.reason }}</p>
      </li>
    </ul>

    <template v-if="pendingFiles.length">
      <fieldset class="defaults">
        <legend>For every file</legend>
        <div class="field">
          <label for="default-categories">Also add categories</label>
          <CategoryPicker
            id="default-categories"
            v-model="defaults.categories"
            :options="availableCategories"
            placeholder="e.g. a course name"
          />
        </div>
        <label class="check"
          ><input v-model="defaults.status" type="checkbox" true-value="public" false-value="draft" /> Publish right away</label
        >
        <label class="check"><input v-model="defaults.downloadable" type="checkbox" /> Allow download</label>
      </fieldset>

      <div v-if="uploading || feedback" class="upload-status">
        <div v-if="uploading" class="progress-bar-track">
          <div class="progress-bar-fill" :style="{ width: progress + '%' }"></div>
        </div>
        <p
          v-if="feedback"
          class="alert"
          :class="`alert-${feedbackVariant === 'danger' ? 'danger' : feedbackVariant === 'success' ? 'success' : 'info'}`"
          data-testid="upload-feedback"
        >
          {{ feedback }}
        </p>
      </div>

      <div class="page-actions">
        <button
          type="button"
          class="btn btn-primary"
          :disabled="uploading"
          data-testid="upload-button"
          @click="handleUpload"
        >
          {{ uploading ? 'Uploading…' : `Upload ${pendingFiles.length} ${pendingFiles.length === 1 ? 'book' : 'books'}` }}
        </button>
      </div>
    </template>
  </div>
</template>

<style scoped src="@/css/views/admin/UploadPdf.css"></style>
