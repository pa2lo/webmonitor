<script setup>
import { onMounted } from 'vue'
import { router } from '@inertiajs/vue3'

import GuestLayout from '@/Layouts/GuestLayout.vue'
import Tag from '@/Components/Elements/Tag.vue'
import IcoButton from '@/Components/Elements/IcoButton.vue'
import Button from '@/Components/Elements/Button.vue'

const props = defineProps({
	websites: Array
})

onMounted(() => {
	setInterval(() => {
		router.reload()
	}, 30000)
})
</script>

<template>
	<GuestLayout header="Monitoring status">
		<div class="line">
			<table class="infoTable">
				<thead>
					<tr>
						<th>Website</th>
						<th class="ta-c wstatus">Status</th>
						<!-- <th class="infoTable-buttons"></th> -->
					</tr>
				</thead>
				<tbody>
					<tr v-for="website in websites">
						<td>
							<span v-tooltip="website.url">{{ website.name }}</span>
						</td>
						<td class="ta-c">
							<Tag v-if="!website.checked_at" color="link">Waiting</Tag>
							<Tag v-else-if="website.status == '200+'" icon="check" color="success" v-tooltip="'Phrase found'">Online</Tag>
							<Tag v-else-if="website.status == 200" icon="x" color="success" v-tooltip="'Phrase missing'">Online</Tag>
							<Tag v-else-if="website.status == 600" color="danger">Timeout</Tag>
							<Tag v-else-if="website.status == 700" color="danger">Offline</Tag>
							<Tag v-else color="warning">{{ website.status }}</Tag>
						</td>
						<!-- <td class="infoTable-buttons">
							<IcoButton icon="external-link" v-tooltip="'Open web'" />
						</td> -->
					</tr>
				</tbody>
			</table>
		</div>
		<div class="line divided ta-c">
			<Button v-if="$page.props.auth.user" full link="/dashboard">Dashboard</Button>
			<Button v-else link="/login" full>Log in</Button>
		</div>
	</GuestLayout>
</template>