<script setup>
import { computed } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { toast } from '@/Utils/toaster'

import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import Card from '@/Components/Elements/Card.vue'
import TextInput from '@/Components/Inputs/TextInput.vue'
import RadioButtons from '@/Components/Inputs/RadioButtons.vue'
import Button from '@/Components/Elements/Button.vue'
import InputsRow from '@/Components/Inputs/InputsRow.vue'
import SelectInput from '@/Components/Inputs/SelectInput.vue'

const props = defineProps({
	settings: Object
})

const form = useForm({
	node_server: props.settings.node_server,
	cron_key: props.settings.cron_key,
	reports: props.settings.reports
})

function save() {
	form.reports = form.reports.filter(f => f.email)

	form.patch('/settings', {
		onSuccess: () => toast.success('Settings updated'),
		onError: (data) => {
			console.log(data)
			toast.error('Failed to save settings')
		}
	})
}

const nodeServerOptions = [
	{
		title: 'Disabled',
		value: false,
		color: 'danger'
	}, {
		title: 'Enabled',
		value: true,
		color: 'success'
	}
]

function addNotification() {
	form.reports.push({
		email: '',
		frequency: 'monthly'
	})
}
function removeNotification(i) {
	form.reports.splice(i, 1)
}

const frequencyOptions =  ['Daily', 'Monthly'].map(v => ({ title: v, value: v.toLowerCase() }))

const cronUrl = computed(() => {
	return `${window.location.origin}/checkAll?key=${props.settings.cron_key}`
})
const mailCronUrl = computed(() => {
	return `${window.location.origin}/mailReport?key=${props.settings.cron_key}`
})
</script>

<template>
	<AuthenticatedLayout header="Settings" width="narrow">
		<Card as="form" header="Global settings" @submit.prevent="save">
			<RadioButtons
				label="Node server"
				sameWidth
				solid
				horizontal
				required
				:options="nodeServerOptions"
				v-model="form.node_server"
				:error="form.errors.node_server"
			/>
			<h3 class="divided input-note-horizontal">Availability reports</h3>
			<template v-if="form.reports.length">
				<InputsRow v-for="(report, i) in form.reports" horizontal :label="`Report ${i+1}`" wrap>
					<TextInput
						:chars="29"
						placeholder="your@email.tld"
						v-model="report.email"
						required
						trim
						class="grow"
					/>
					<InputsRow class="grow">
						<SelectInput
							:options="frequencyOptions"
							v-model="report.frequency"
							class="grow"
						/>
						<Button icon="x" v-tooltip="'Delete'" bigIcon color="danger" variant="outline" @click="removeNotification(i)" />
					</InputsRow>
				</InputsRow>
			</template>
			<InputsRow horizontal>
				<Button variant="outline" color="link" icon="plus" @click.prevent="addNotification">Add report</Button>
			</InputsRow>
			<h3 class="divided input-note-horizontal">Cron settings</h3>
			<TextInput
				label="Cron key"
				name="wm_cron_key"
				autocomplete="off"
				horizontal
				required
				trim
				v-model="form.cron_key"
				:error="form.errors.cron_key"
			/>
			<TextInput
				label="Monitoring Cron URL"
				horizontal
				readOnly
				copyable
				:modelValue="cronUrl"
				tooltip="Visit this URL with Cron Job every minute"
			/>
			<TextInput
				v-if="form.reports.length"
				label="Reports Cron URL"
				horizontal
				readOnly
				copyable
				:modelValue="mailCronUrl"
				tooltip="Visit this URL with Cron Job every day"
			/>
			<template #buttons>
				<Button type="submit" :loading="form.processing">Save settings</Button>
			</template>
		</Card>
	</AuthenticatedLayout>
</template>

<style>
	.input-horizontal .input-radio-buttons-inner {
		min-width: 14.5rem;
	}
</style>