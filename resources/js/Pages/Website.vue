<script setup>
import { ref, onMounted } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import { formatDate } from '@/Utils/helpers'
import { toast } from '@/Utils/toaster'
import { serverOptions, timeoutOptions, rpaOptions, updateOptions, daysMap, monthsMap } from '@/Utils/consts'

import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import Card from '@/Components/Elements/Card.vue'
import Tag from '@/Components/Elements/Tag.vue'
import Button from '@/Components/Elements/Button.vue'
import IcoButton from '@/Components/Elements/IcoButton.vue'
import Modal from '@/Components/Modals/Modal.vue'
import TextInput from '@/Components/Inputs/TextInput.vue'
import RadioButtons from '@/Components/Inputs/RadioButtons.vue'
import Loader from '@/Components/Elements/Loader.vue'
import Icon from '@/Components/Elements/Icon.vue'
import WebsiteChartMins from './Partials/WebsiteChartMins.vue'
import WebsiteChartDays from './Partials/WebsiteChartDays.vue'
import WebsiteChartHours from './Partials/WebsiteChartHours.vue'
import Tabs from '@/Components/Tabs/Tabs.vue'
import Tab from '@/Components/Tabs/Tab.vue'
import SlideToggle from '@/Components/Elements/SlideToggle.vue'

const props = defineProps({
	website: Object,
	hasNodeServer: Boolean
})

const editFormModal = ref(false)
const monthStatsModal = ref(false)
const dayStatsModal = ref(false)
const form = useForm({
	name: props.website.name,
	url: props.website.url,
	phrase: props.website.phrase,
	active: props.website.active,
	mserver: props.website.server,
	mrequests: props.website.requests,
	mtimeout: props.website.timeout,
})
function showEditForm() {
	form.clearErrors()
	form.reset()
	editFormModal.value = true
}
function submit() {
	form.patch(`/websites/${props.website.id}`, {
		preserveScroll: true,
		onSuccess: () => {
			toast.success('Website updated successfully')
			editFormModal.value = false
		}
	})
}

const monthStatsLoaded = ref(false)
const monthStatsInfo = ref({})
const monthStatsChart = ref(null)
const monthStatsData = ref([])
function showMonthStats(year, month) {
	monthStatsInfo.value = { year, month }
	monthStatsLoaded.value = false
	monthStatsModal.value = true
	monthStatsChart.value = null

	axios.post(`/websites/${props.website.id}/monthData`, {
		year, month
	}).then((res) => {
		if (res.data?.length) {
			monthStatsData.value = res.data
			let monthDays = []
			res.data.forEach(w => monthDays.push(...w.days))
			if (monthDays?.length > 2) {
				monthDays.reverse()
				monthStatsChart.value = {
					success_rate: monthDays.map(d => ({x: d.date, y: parseFloat((d.ok / d.attempts * 100).toFixed(2))})),
					avg_duration: monthDays.map(d => ({x: d.date, y: d.avg_duration}))
				}
			}
			monthStatsLoaded.value = true
		} else {
			monthStatsModal.value = false
			toast.error('Failed to load data')
		}
	}).catch((err) => {
		console.log(err)
		monthStatsModal.value = false
		toast.error('Failed to load data')
	})
}

const dayStatsLoaded = ref(false)
const dayStatsInfo = ref(null)
const dayStatsData = ref([])
const dayStatsChartMins = ref(null)
const dayStatsChartHours = ref(null)
const dayStatsExpandedHours = ref([])
function showDayStats(day) {
	dayStatsLoaded.value = false
	dayStatsInfo.value = day
	dayStatsExpandedHours.value = []
	dayStatsModal.value = true
	dayStatsChartMins.value = null
	dayStatsChartHours.value = null

	axios.post(`/websites/${props.website.id}/dayData`, {
		date: day.date
	}).then((res) => {
		if (res.data?.length) {
			dayStatsData.value = res.data
			if (res.data.length == 1) dayStatsExpandedHours.value.push(res.data[0].hour)
			const allAttempts = res.data.flatMap(m => m.items.map(n => ({x: n.created_at, y: n.duration})))
			if (allAttempts.length > 20) dayStatsChartMins.value = allAttempts
			if (res.data.length > 2) dayStatsChartHours.value = {
				min: res.data.map(d => ({x: d.hour, y: d.min_duration})),
				max: res.data.map(d => ({x: d.hour, y: d.max_duration}))
			}
			dayStatsData.value.forEach((h) => {
				h.hasRedirects = h.items.some(i => i.redirected)
			})
			dayStatsLoaded.value = true
		} else {
			dayStatsModal.value = false
			toast.error('Failed to load data')
		}
	}).catch((err) => {
		console.log(err)
		dayStatsModal.value = false
		toast.error('Failed to load data')
	})
}
function toggleDayStatsHour(hour) {
	if (dayStatsExpandedHours.value.includes(hour)) dayStatsExpandedHours.value = dayStatsExpandedHours.value.filter(h => h != hour)
	else dayStatsExpandedHours.value.push(hour)
}

const refreshing = ref(false)
function checkNow() {
	refreshing.value = true
	axios.get(`/websites/${props.website.id}/check`).then((res) => {
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

		router.reload()
		loadStats()
	}).catch((err) => {
		toast.error('Refresh failed', {
			message: err?.data?.message || null
		})
		console.log(err)
	}).finally(() => {
		refreshing.value = false
	})
}

const chartData = ref(null)
const dailyStats = ref([])
const dailyStatsLoaded = ref(false)
const stats = ref({})
const statsLoaded = ref(false)

onMounted(() => {
	loadDailyStats()
	loadStats()
})

function loadDailyStats() {
	axios.get(`/websites/${props.website.id}/dailyStats`).then((res) => {
		dailyStats.value = res.data
		chartData.value = {
			success_rate: res.data.map(s => ({x: s.date, y: s.success_rate})),
			avg_duration: res.data.map(s => ({x: s.date, y: s.avg_duration}))
		}
		dailyStatsLoaded.value = true
	})
}
function loadStats() {
	axios.get(`/websites/${props.website.id}/stats`).then((res) => {
		stats.value = res.data
		statsLoaded.value = true
	})
}
</script>

<template>
	<AuthenticatedLayout :header="website.name + ' stats'" :backLink="'/dashboard'" width="narrow">
		<Card>
			<template #header>
				<div class="flex ai-c">
					<h3>Info</h3>
					<div class="buttons-row ml-a">
						<template v-if="website.active">
							<Tag v-if="!website.checked_at" color="link">Waiting</Tag>
							<Tag v-else-if="website.status == '200+'" icon="check" color="success" v-tooltip="'Phrase found'">Online</Tag>
							<Tag v-else-if="website.status == 200" icon="x" color="success" v-tooltip="'Phrase missing'">Online</Tag>
							<Tag v-else-if="website.status == 600" color="danger">Timeout</Tag>
							<Tag v-else-if="website.status == 700" color="danger">Offline</Tag>
							<Tag v-else color="warning">{{ website.status }}</Tag>
						</template>
						<Tag v-if="!website.active" color="warning" solid>Disabled</Tag>
						<Tag v-else color="success" solid>Active</Tag>
					</div>
				</div>
			</template>
			<div class="info-cols">
				<div>
					<div>URL - <a class="ico-link" rel="noopener noreferrer" target="_blank" :href="website.url"><strong>{{ website.url }}</strong></a></div>
					<div>Phrase - <strong>{{ website.phrase }}</strong></div>
					<div>Active - <strong>{{ website.active ? 'yes' : 'no' }}</strong></div>
					<div>Monitoring server - <strong>{{ website.server == 'php' ? 'PHP' : 'NodeJS' }}</strong></div>
					<div>Request timeout - <strong>{{ website.timeout }}s</strong></div>
					<div v-if="website.server == 'node'">Requests per attempt - <strong>{{ website.requests }}x</strong></div>
				</div>
				<div>
					<div>Attempts - <strong>{{ website.attempts }}</strong></div>
					<div v-if="website.checked_at && website?.downtimes_count > 0">Downtimes - <strong>{{ website.downtimes_count }}</strong></div>
					<div>Created at - <strong>{{ formatDate(website.created_at) }}</strong></div>
					<div>Updated at - <strong>{{ formatDate(website.updated_at) }}</strong></div>
					<div v-if="website.checked_at">Last checked - <strong>{{ formatDate(website.checked_at) }}</strong></div>
				</div>
			</div>
			<p class="buttons-row">
				<Button icon="edit" @click.prevent="showEditForm" :disabled="refreshing">Edit</Button>
				<Button icon="external-link" :link="website.url" target="_blank" rel="noopener noreferrer" variant="outline" color="link">Open web</Button>
				<Button icon="refresh" @click.prevent="checkNow" variant="outline" color="link" :loading="refreshing">Check now</Button>
				<Button v-if="website?.downtimes_count > 0" icon="cloud-off" variant="outline" color="link" :link="`/downtimes?website_id=${website.id}`">Downtimes</Button>
			</p>
			<template v-if="website.attempts > 0">
				<h3 class="divided">Stats</h3>
				<Loader class="line" :loading="!dailyStatsLoaded || !statsLoaded">
					<template v-if="dailyStatsLoaded && statsLoaded">
						<WebsiteChartDays v-if="dailyStats.length > 3" :data="chartData" />
						<div class="line">
							<div v-for="data in stats" class="dataTable-wrapper line">
								<table class="infoTable infoTable-stats">
									<thead>
										<tr>
											<th class="w6">{{ data.year }}</th>
											<th v-tooltip="'Attempts'" class="ta-c">{{ data.attempts }}x</th>
											<th v-tooltip="'Avg Response'" class="ta-c">{{ data.avg_duration }}ms</th>
											<th v-tooltip="'Availability'" class="ta-r wstatus">
												<Tag :color="data.ok == data.attempts ? 'success' : 'warning'">{{ parseFloat((data.ok / data.attempts * 100).toFixed(2)) }}%</Tag>
											</th>
										</tr>
									</thead>
									<tbody>
										<tr v-for="monthData in data.months" @click="showMonthStats(data.year, monthData.month)" class="dataTable-row isClickable">
											<td>
												<span class="basic-link">{{ monthsMap[monthData.month] }}</span>
											</td>
											<td class="ta-c">{{ monthData.attempts }}x</td>
											<td class="ta-c">{{ monthData.avg_duration }}ms</td>
											<td class="ta-r wstatus">
												<Tag :color="monthData.ok == monthData.attempts ? 'success' : 'warning'"><strong>{{ parseFloat((monthData.ok / monthData.attempts * 100).toFixed(2)) }}%</strong></Tag>
											</td>
										</tr>
									</tbody>
								</table>
							</div>
						</div>
					</template>
				</Loader>
			</template>
		</Card>
		<Modal v-model:open="editFormModal" header="Edit website" as="form" :closeable="!form.processing" @submit.prevent="submit" width="narrow">
			<TextInput
				label="Name"
				placeholder="yourwebsite.com"
				v-model="form.name"
				:error="form.errors.name"
				trim
				required
				autofocus
			/>
			<TextInput
				label="URL"
				placeholder="https://www.someurl.com"
				v-model="form.url"
				trim
				required
				:error="form.errors.url"
			/>
			<TextInput
				label="Search phrase"
				placeholder="some text on the site"
				v-model="form.phrase"
				trim
				required
				:error="form.errors.phrase"
			/>
			<RadioButtons
				v-if="hasNodeServer"
				label="Monitoring server"
				sameWidth
				:options="serverOptions"
				v-model="form.mserver"
				:error="form.errors.mserver"
			/>
			<SlideToggle :show="form.mserver == 'node'" class="line">
				<RadioButtons
					label="Requests per attempt"
					sameWidth
					:options="rpaOptions"
					v-model="form.mrequests"
					:error="form.errors.mrequests"
				/>
			</SlideToggle>
			<RadioButtons
				label="Request timeout"
				sameWidth
				:options="timeoutOptions"
				v-model="form.mtimeout"
				:error="form.errors.mtimeout"
			/>
			<RadioButtons
				label="Monitoring"
				solid
				:options="updateOptions"
				v-model="form.active"
				:error="form.errors.active"
			/>
			<p>
				<Button full type="submit" :loading="form.processing">Save website</Button>
			</p>
		</Modal>
		<Modal v-model:open="monthStatsModal" :header="monthStatsInfo?.month ? `Stats ${monthsMap[monthStatsInfo.month]} ${monthStatsInfo.year}` : 'Month stats'">
			<Loader :loading="!monthStatsLoaded">
				<template v-if="monthStatsLoaded">
					<WebsiteChartDays v-if="monthStatsChart" :data="monthStatsChart" />
					<div v-for="week in monthStatsData" class="dataTable-wrapper line">
						<table class="infoTable infoTable-stats">
							<thead>
								<tr>
									<th class="w65">{{ week.from }} - {{ week.to }}</th>
									<th v-tooltip="'Attempts'" class="ta-c">{{ week.attempts }}x</th>
									<th v-tooltip="'Avg Response'" class="ta-c">{{ week.avg_duration }}ms</th>
									<th v-tooltip="'Availability'" class="ta-r wstatus">
										<Tag :color="week.ok == week.attempts ? 'success' : 'warning'">{{ parseFloat((week.ok / week.attempts * 100).toFixed(2)) }}%</Tag>
									</th>
								</tr>
							</thead>
							<tbody>
								<tr v-for="day in week.days" class="dataTable-row isClickable" @click="showDayStats(day)">
									<td>
										<span class="basic-link">{{ daysMap[day.weekday] }} - {{ day.day }}.{{ monthStatsInfo.month }}</span>
									</td>
									<td class="ta-c">{{ day.attempts }}x</td>
									<td class="ta-c">{{ day.avg_duration }}ms</td>
									<td class="ta-r wstatus">
										<Tag :color="day.ok == day.attempts ? 'success' : 'warning'"><strong>{{ parseFloat((day.ok / day.attempts * 100).toFixed(2)) }}%</strong></Tag>
									</td>
								</tr>
							</tbody>
						</table>
					</div>
				</template>
			</Loader>
		</Modal>
		<Modal v-model:open="dayStatsModal" :header="dayStatsInfo?.day ? `Stats ${daysMap[dayStatsInfo.weekday]} ${dayStatsInfo.day} ${monthsMap[monthStatsInfo.month]} ${monthStatsInfo.year}` : 'Day stats'">
			<Loader :loading="!dayStatsLoaded">
				<template v-if="dayStatsLoaded">
					<Tabs v-if="dayStatsChartHours || dayStatsChartMins" grow>
						<Tab v-if="dayStatsChartHours" name="Hours">
							<WebsiteChartHours v-if="dayStatsChartHours" :data="dayStatsChartHours" />
						</Tab>
						<Tab v-if="dayStatsChartMins" name="Minutes">
							<WebsiteChartMins v-if="dayStatsChartMins" :data="dayStatsChartMins" />
						</Tab>
					</Tabs>
					<div class="line">
						<div v-for="hour in dayStatsData" class="dataTable-wrapper smaller-line">
							<table class="infoTable infoTable-stats">
								<thead>
									<tr @click="toggleDayStatsHour(hour.hour)" class="isClickable">
										<th class="w9">{{ hour.hour }} - {{ hour.hour+1 }}:00 - {{ hour.attempts }}x</th>
										<th v-tooltip="'Response from - to'" class="ta-c">{{ hour.min_duration }} - {{ hour.max_duration }}ms</th>
										<th v-if="hour.hasRedirects" class="infoTable-buttons" style="width: 2.5rem;"></th>
										<th v-tooltip="'Availability'" class="ta-c wstatus">
											<Tag :color="hour.ok == hour.attempts ? 'success' : 'warning'">{{ parseFloat((hour.ok / hour.attempts * 100).toFixed(2)) }}%</Tag>
										</th>
										<th class="infoTable-buttons" style="width: 3rem;">
											<IcoButton v-if="dayStatsExpandedHours.includes(hour.hour)" icon="minus" v-tooltip="'Collapse'" @click.stop="toggleDayStatsHour(hour.hour)" />
											<IcoButton v-else icon="plus" v-tooltip="'Expand'" @click.stop="toggleDayStatsHour(hour.hour)" />
										</th>
									</tr>
								</thead>
								<tbody v-if="dayStatsExpandedHours.includes(hour.hour)">
									<tr v-for="item in hour.items">
										<td>{{ item.realTime }}</td>
										<td class="ta-c">{{ item.duration }}ms</td>
										<td v-if="hour.hasRedirects" class="infoTable-buttons fz1 ta-c">
											<Icon v-if="item.redirected" name="redirect" v-tooltip="'Redirected'" />
										</td>
										<td class="ta-c wstatus">
											<Tag v-if="item.status == 200" color="success">{{ item.status }}</Tag>
											<Tag v-else-if="item.status == 600" color="danger">Timeout</Tag>
											<Tag v-else-if="item.status == 700" color="danger">Offline</Tag>
											<Tag v-else color="warning">{{ item.status }}</Tag>
										</td>
										<td class="infoTable-buttons fz1 ta-c">
											<Icon v-if="item.status == 200" :name="item.phrase ? 'check' : 'x'" v-tooltip="item.phrase ? 'Phrase found' : 'Phrase missing'" />
										</td>
									</tr>
								</tbody>
							</table>
						</div>
					</div>
				</template>
			</Loader>
		</Modal>
	</AuthenticatedLayout>
</template>