<script setup>
import Pagination from './Pagination.vue'

const props = defineProps({
	data: Object
})
</script>

<template>
	<Pagination
		v-if="data?.links"
		:links="data.links"
		:pages="data.last_page"
		:currentPage="data.current_page"
		:prevPage="data.prev_page_url"
		:nextPage="data.next_page_url"
		:firstPage="data.first_page_url"
		:lastPage="data.last_page_url"
		:from="data.from"
		:to="data.to"
		:total="data.total"
	/>
</template>