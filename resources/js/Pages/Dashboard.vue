<script setup>
import { ref } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import { toast } from '@/Utils/toaster'
import { dialog } from '@/Utils/dialog'
import { useAppForms } from '@/Composables/AppForms'
import { serverOptions, timeoutOptions, rpaOptions, updateOptions } from '@/Utils/consts'

import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import TableInfo from '@/Components/Table/TableInfo.vue'
import DataTable from '@/Components/Table/DataTable.vue'
import Column from '@/Components/Table/Column.vue'
import Card from '@/Components/Elements/Card.vue'
import TextInput from '@/Components/Inputs/TextInput.vue'
import Button from '@/Components/Elements/Button.vue'
import Modal from '@/Components/Modals/Modal.vue'
import Tag from '@/Components/Elements/Tag.vue'
import RadioButtons from '@/Components/Inputs/RadioButtons.vue'
import SimpleToggle from '@/Components/Inputs/SimpleToggle.vue'
import IcoButton from '@/Components/Elements/IcoButton.vue'
import SlideToggle from '@/Components/Elements/SlideToggle.vue'
import Icon from '@/Components/Elements/Icon.vue'

const filter = ref('')

const props = defineProps({
	websites: Array,
	hasNodeServer: Boolean
})

const { showNewForm, showEditForm, activeForm, showModal } = useAppForms({
	name: '',
	url: '',
	phrase: '',
	mserver: 'php',
	mrequests: 3,
	mtimeout: 10,
	active: true
})

function showEditFormHelper(data) {
	const clone = JSON.parse(JSON.stringify(data))
	clone.mserver = data.server
	clone.mrequests = data.requests
	clone.mtimeout = data.timeout
	showEditForm(clone)
}

function submit() {
	activeForm.value.form.clearErrors()
	activeForm.value.type == 'newForm' ? submitNewForm() : submitEditForm()
}

function submitNewForm() {
	activeForm.value.form.post('/websites', {
		preserveScroll: true,
		onSuccess: () => afterFormSubmit('Website added successfully')
	})
}
function submitEditForm() {
	if (!activeForm.value.form.id) return

	activeForm.value.form.patch(`/websites/${activeForm.value.form.id}`, {
		preserveScroll: true,
		onSuccess: () => afterFormSubmit('Website updated successfully')
	})
}

function afterFormSubmit(message) {
	toast.success(message)
	showModal.value = false
}

const loadingRows = ref([])

function switchActive(e, id) {
	if (!id) return

	sendAxiosRequest('post', `/websites/${id}/switch`, {
		active: e.target.checked
	}, id, null, () => {
		router.reload({only: ['websites']})
	})
}
function deleteItem(data) {
	if (!data?.id) return

	dialog.delete('Delete website monitoring', `Are you sure you want to delete <strong>${data.name}</strong> website monitoring?`, {
		onConfirm: () => {
			sendAxiosRequest('delete', `/websites/${data.id}`, {}, data.id, 'Website monitoring deleted', () => {
				router.reload({only: ['websites']})
			})
		}
	})
}

function sendAxiosRequest(method, url, data, id, successMessage, successCallback) {
	if (id) loadingRows.value.push(id)

	axios[method](url, data).then((response) => {
		if (response?.data?.success) {
			toast.success(successMessage || 'Website settings updated')
			if (successCallback) successCallback(response)
		} else {
			toast.error('Operation failed!')
			console.log(response)
		}
	}).catch((err) => {
		toast.error('Operation failed!')
		console.log(err)
	}).finally(() => {
		if (id) loadingRows.value = loadingRows.value.filter(i => i != id)
	})
}

function checkNow(id) {
	if (!id) return

	loadingRows.value.push(id)
	axios.get(`/websites/${id}/check`).then((res) => {
		let data = res.data

		if (data?.status_code == 200) {
			toast.success('Site online', {
				message: `Responded in ${data?.response_time_ms}ms<br>${data?.phrase_found ? 'Phrase found' : 'Phrase not found'}`
			})
		} else if (data?.status_code == 600)  {
			toast.error('Request timeout', {
				message: data?.error || null
			})
		} else if (data?.status_code == 700)  {
			toast.error('Site offline', {
				message: data?.error || null
			})
		} else {
			toast.warning(`Site responded with code ${data?.status_code}`, {
				message: `Responded in ${data?.response_time_ms}ms`
			})
		}

		router.reload({only: ['websites']})
	}).catch((err) => {
		toast.error('Refresh failed', {
			message: err?.data?.message || null
		})
		console.log(err)
	}).finally(() => {
		loadingRows.value = loadingRows.value.filter(i => i != id)
	})
}
</script>

<template>
	<AuthenticatedLayout header="Websites">
		<Card>
			<TableInfo v-if="websites?.length" :count="websites.length">
				<!-- <TextInput placeholder="Filter..." v-model="filter" icon="search" clearable /> -->
				<template #buttons>
					<Button icon="plus" @click="showNewForm">Add website</Button>
				</template>
			</TableInfo>
			<DataTable :items="websites ?? []" :loadingRows="loadingRows" modelField="id">
				<template #empty>
					<Button v-if="filter" icon="x" variant="outline" @click="filter = ''">Reset filter</Button>
					<Button v-else icon="plus" @click.prevent="showNewForm">Add website</Button>
				</template>
				<Column v-if="hasNodeServer || websites.some(i => i.server == 'node')" type="icon">
					<template #default="{ data }">
						<Icon :name="data.server" />
					</template>
				</Column>
				<Column header="Name" field="name" link="/websites" linkParam="id" />
				<Column header="Status" align="center">
					<template #default="{ data }">
						<Tag v-if="!data.active" color="warning">Disabled</Tag>
						<Tag v-else-if="!data.checked_at" color="link">Waiting</Tag>
						<Tag v-else-if="data.status == '200+'" icon="check" color="success" v-tooltip="'Phrase found'">Online</Tag>
						<Tag v-else-if="data.status == 200" icon="x" color="success" v-tooltip="'Phrase missing'">Online</Tag>
						<Tag v-else-if="data.status == 600" color="danger">Timeout</Tag>
						<Tag v-else-if="data.status == 700" color="danger">Offline</Tag>
						<Tag v-else color="warning">{{ data.status }}</Tag>
					</template>
				</Column>
				<Column header="Active" align="center">
					<template #default="{ data }">
						<SimpleToggle :modelValue="data.active" @change="switchActive($event, data.id)" />
					</template>
				</Column>
				<Column header="Attempts" field="attempts" align="center" />
				<Column header="Last checked" field="checked_at" type="date" />
				<Column type="buttons">
					<template #default="{ data }">
						<IcoButton icon="right" link="/websites" :linkParam="data.id" v-tooltip="'Website stats'" />
						<IcoButton icon="edit" @click.stop="showEditFormHelper(data)" v-tooltip="'Edit'" />
						<IcoButton icon="refresh" @click.stop="checkNow(data.id)" v-tooltip="'Check now'" />
						<IcoButton :link="data.url" icon="external-link" target="_blank" rel="noopener noreferrer" v-tooltip="'Open web'" />
						<IcoButton icon="trash" color="danger" v-tooltip="'Delete'" @click.stop="deleteItem(data)" />
					</template>
				</Column>
			</DataTable>
		</Card>
		<Modal v-model:open="showModal" width="narrow" as="form" :header="activeForm.type == 'newForm' ? 'Add new website' : 'Edit website'" :closeable="!activeForm.form.processing" @submit.prevent="submit">
			<TextInput
				label="Name"
				placeholder="yourwebsite.com"
				v-model="activeForm.form.name"
				:error="activeForm.form.errors.name"
				trim
				required
				autofocus
			/>
			<TextInput
				label="URL"
				placeholder="https://www.someurl.com"
				v-model="activeForm.form.url"
				trim
				required
				:error="activeForm.form.errors.url"
			/>
			<TextInput
				label="Search phrase"
				placeholder="some text on the site"
				v-model="activeForm.form.phrase"
				trim
				required
				:error="activeForm.form.errors.phrase"
			/>
			<RadioButtons
				v-if="hasNodeServer"
				label="Monitoring server"
				sameWidth
				:options="serverOptions"
				v-model="activeForm.form.mserver"
				:error="activeForm.form.errors.mserver"
			/>
			<SlideToggle :show="activeForm.form.mserver == 'node'" class="line">
				<RadioButtons
					label="Requests per attempt"
					sameWidth
					:options="rpaOptions"
					v-model="activeForm.form.mrequests"
					:error="activeForm.form.errors.mrequests"
				/>
			</SlideToggle>
			<RadioButtons
				label="Request timeout"
				sameWidth
				:options="timeoutOptions"
				v-model="activeForm.form.mtimeout"
				:error="activeForm.form.errors.mtimeout"
			/>
			<RadioButtons
				label="Monitoring"
				sameWidth
				solid
				:options="updateOptions"
				v-model="activeForm.form.active"
				:error="activeForm.form.errors.active"
			/>
			<p>
				<Button full type="submit" :loading="activeForm.form.processing">Save website</Button>
			</p>
		</Modal>
	</AuthenticatedLayout>
</template>