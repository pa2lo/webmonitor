<script setup>
import { computed, ref } from 'vue'
import { router } from '@inertiajs/vue3'
import { formatDate } from '@/Utils/helpers'
import { toast } from '@/Utils/toaster'

import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import Card from '@/Components/Elements/Card.vue'
import DataTable from '@/Components/Table/DataTable.vue'
import Column from '@/Components/Table/Column.vue'
import TableInfo from '@/Components/Table/TableInfo.vue'
import Tag from '@/Components/Elements/Tag.vue'
import IcoButton from '@/Components/Elements/IcoButton.vue'
import Modal from '@/Components/Modals/Modal.vue'
import Loader from '@/Components/Elements/Loader.vue'
import Button from '@/Components/Elements/Button.vue'
import Accordion from '@/Components/Elements/Accordion.vue'
import LaravelPagination from '@/Components/Elements/LaravelPagination.vue'
import SelectInput from '@/Components/Inputs/SelectInput.vue'
import FilterTags from '@/Components/Table/FilterTags.vue'

const props = defineProps({
	downtimes: Object,
	websites: Array,
	filters: Object,
	statuses: Array
})

const isLoading = ref(false)
const websiteFilter = ref({
	website_id: props.filters?.website_id || '',
	status: props.filters?.status || []
})
function setFilter(e) {
	let params = Object.entries(websiteFilter.value).reduce((acc, [k, v]) => {
		if (v) acc[k] = v
		return acc
	}, {})
	router.get(window.location.pathname, params, {
		preserveState: true,
		preserveScroll: true,
		onStart: () => isLoading.value = true,
		onFinish: () => isLoading.value = false
	})
}
function clearFilter() {
	websiteFilter.value.website_id = ''
	websiteFilter.value.status = []
	setFilter()
}
function clearFilterKey(key, code) {
	if (key == 'website_id') websiteFilter.value.website_id = ''
	else if (key == 'tag', code) websiteFilter.value.status = websiteFilter.value.status.filter(c => c != code)
	setFilter()
}

const websitesMap = props.websites.reduce((acc, web) => {
	acc[web.id] = {
		name: web.name,
		url: web.url
	}
	return acc
}, {})
const websitesFilterOptions = props.websites.map(w => ({ title: w.name, value: w.id }))
const statusFilterOptions = props.statuses.sort().map(s => {
	let title = s.toString()
	if (s == 600) title = 'Timeout'
	else if (s == 700) title = 'Offline'
	return {
		title,
		value: s
	}
})

function formatDateFtomTo(dateFrom, dateTo) {
	let from = new Date(dateFrom*1000).toLocaleString('sk', { hour: '2-digit', minute: '2-digit', second: '2-digit' })
	let to = new Date(dateTo*1000).toLocaleString('sk', { hour: '2-digit', minute: '2-digit', second: '2-digit' })
	let date = new Date(dateFrom*1000).toLocaleString('sk', { year: 'numeric', month: 'numeric', day: 'numeric' })
	return `${date}: ${from} - ${to}`
}

function formatHMFromTime(from, to) {
	let mins = Math.round((to - from) / 60)

	if (mins < 60) return `${mins}m`
	let m = mins % 60
	let h = Math.floor(mins / 60)

	return `${h}h ${m > 0 ? ` ${m}m` : ''}`
}

function formatHMS(time) {
	return new Date(time).toLocaleString('sk', { hour: '2-digit', minute: 'numeric', second: 'numeric' })
}

const dtDataModal = ref(false)
const dtDataLoaded = ref(false)
const dtInfo = ref({})
const dtData = ref([])
function showDowntimeRecords(data) {
	dtDataLoaded.value = false
	dtData.value = []
	dtInfo.value = {
		site: websitesMap[data.website_id].name,
		id: data.website_id,
		url: websitesMap[data.website_id].url,
		time: formatDate(data.from, true),
		body: data?.body ?? null
	}
	dtDataModal.value = true

	axios.post('/downtimes/getRecords', {
		ids: data.attempts
	}).then((res) => {
		if (res.data?.length) {
			dtData.value = res.data
			dtDataLoaded.value = true
		} else {
			dtDataModal.value = false
			toast.error('Failed to load data')
		}
	}).catch((err) => {
		console.log(err)
		dtDataModal.value = false
		toast.error('Failed to load data')
	})
}

const hasFilter = computed(() => Object.values(websiteFilter.value).some(v => v))
</script>

<template>
	<AuthenticatedLayout header="Downtimes Log">
		<Card>
			<TableInfo v-if="downtimes.data?.length || hasFilter || isLoading" :count="downtimes.total">
				<SelectInput allowEmpty placeholder="All websites" :options="websitesFilterOptions" v-model="websiteFilter.website_id" @change="setFilter" searchable />
				<SelectInput showCount placeholder="All statuses" :options="statusFilterOptions" v-model="websiteFilter.status" @change="setFilter" />
			</TableInfo>
			<FilterTags>
				<Tag v-if="websiteFilter.website_id" key="fwid" clearable @click="clearFilterKey('website_id')">Website: {{ websitesMap[websiteFilter.website_id].name }}</Tag>
				<template v-if="websiteFilter.status.length" v-for="code in websiteFilter.status">
					<Tag v-if="code == 600" key="fwcd-600" clearable @click="clearFilterKey('code', 600)" >Status: Timeout</Tag>
					<Tag v-else-if="code == 700" key="fwcd-700" clearable @click="clearFilterKey('code', 700)" >Status: Offline</Tag>
					<Tag v-else :key="`fwcd-${code}`" clearable @click="clearFilterKey('code', code)" >Status: {{ code }}</Tag>
				</template>
			</FilterTags>
			<Loader class="line" :loading="isLoading">
				<DataTable :items="downtimes.data">
					<template #empty>
						<Button v-if="hasFilter" icon="x" variant="outline" color="link" @click.prevent="clearFilter">Reset filter</Button>
					</template>
					<Column header="Website" :colClick="(data) => showDowntimeRecords(data)">
						<template #default="{ data }">
							<span class="basic-link">{{ websitesMap[data.website_id].name }}</span>
						</template>
					</Column>
					<Column header="Time" align="center" cellClass="dataTable-col-date">
						<template #default="{ data }">
							{{ formatDateFtomTo(data.from, data.to) }}
						</template>
					</Column>
					<Column header="Downtime" align="center">
						<template #default="{ data }">
							{{ formatHMFromTime(data.from, data.to) }}
						</template>
					</Column>
					<Column header="Status" align="center">
						<template #default="{ data }">
							<Tag v-if="data.status == 600" color="danger">Timeout</Tag>
							<Tag v-else-if="data.status == 700" color="danger">Offline</Tag>
							<Tag v-else color="warning">{{ data.status }}</Tag>
						</template>
					</Column>
					<Column type="buttons">
						<template #default="{ data }">
							<IcoButton icon="article" v-tooltip="'Downtime records'" @click="showDowntimeRecords(data)" />
							<IcoButton :link="websitesMap[data.website_id].url" icon="external-link" target="_blank" rel="noopener noreferrer" v-tooltip="'Open web'" />
							<IcoButton link="/websites" :linkParam="data.website_id" icon="right" v-tooltip="'Website stats'" />
						</template>
					</Column>
				</DataTable>
				<LaravelPagination :data="downtimes" />
			</Loader>
		</Card>
		<Modal v-model:open="dtDataModal" :header="dtInfo?.site ? `${dtInfo.site} - downtime records` : 'Downtime records'" :headerNote="dtInfo?.time || null">
			<Loader :loading="!dtDataLoaded">
				<template v-if="dtDataLoaded">
					<Accordion v-if="dtInfo?.body" title="Response body" pre>
						{{ dtInfo.body }}
					</Accordion>
					<table class="infoTable infoTable-stats line">
						<tbody>
							<tr v-for="row in dtData">
								<td class="w9">{{ formatHMS(row.created_at) }}</td>
								<td class="ta-c">{{ row.duration }}ms</td>
								<td class="wstatus ta-c">
									<Tag v-if="row.status == 600" color="danger">Timeout</Tag>
									<Tag v-else-if="row.status == 700" color="danger">Offline</Tag>
									<Tag v-else color="warning">{{ row.status }}</Tag>
								</td>
							</tr>
						</tbody>
					</table>
				</template>
			</Loader>
			<template #buttons>
				<Button variant="outline" :link="dtInfo.url" color="link" rel="noopener noreferrer" target="_blank" icon="external-link">Open web</Button>
				<Button link="/websites" :linkParam="dtInfo.id" iconRight="right">Website stats</Button>
			</template>
		</Modal>
	</AuthenticatedLayout>
</template>