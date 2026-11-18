<script setup>
import { computed, shallowRef } from 'vue'
import { Line } from 'vue-chartjs'
import { Chart as ChartJS, Tooltip, Legend, LineElement, PointElement, LinearScale, TimeScale, Filler } from 'chart.js'
import 'chartjs-adapter-date-fns'

ChartJS.register( Tooltip, Legend, LineElement, PointElement, LinearScale, TimeScale, Filler )

const props = defineProps({
	data: Object,
	options: Object,
	algorithmUpdates: Object
})

props.options.plugins.tooltip.callbacks.labelColor = function (context) {
	return {
		borderColor: context.dataset.pointBackgroundColor,
		backgroundColor: context.dataset.pointBackgroundColor,
		borderWidth: 0
	}
}

const legendMargin = {
	id: 'paddingBelowLegends',
	beforeInit: function(chart, options) {
		const originalFit = chart.legend.fit
		chart.legend.fit = function fit() {
			if (originalFit) originalFit.call(this)
			return this.height += 4
		}
	}
}

const mouseline = {
	id: 'mouseLine',
	beforeDatasetsDraw(chart, args, plugins) {
		const { ctx, chartArea: { top, bottom, height } } = chart

		ctx.save()
		ctx.setLineDash([5, 4])

		chart.getDatasetMeta(0).data.forEach((dataPoint, index) => {
			if (dataPoint.active == true) {
				ctx.beginPath()
				ctx.strokeStyle = "#8886"
				ctx.moveTo(dataPoint.x, top)
				ctx.lineTo(dataPoint.x, bottom)
				ctx.stroke()
			}
		})

		ctx.setLineDash([])
		ctx.restore()
	}
}

const chartLayout = shallowRef(null)
const updateMarkerLayout = {
	id: 'updateMarkerLayout',
	afterUpdate(chart) {
		chartLayout.value = { x: chart.scales.x, bottom: chart.chartArea.bottom }
	}
}
const plugins = [mouseline, legendMargin, updateMarkerLayout]

const updateMarkers = computed(() => {
	if (!chartLayout.value || !props.algorithmUpdates) return []

	const { x } = chartLayout.value
	return Object.entries(props.algorithmUpdates).sort(([first], [second]) => first.localeCompare(second))
		.flatMap(([date, description]) => {
			const timestamp = x.parse(date)
			if (timestamp === null || timestamp < x.min || timestamp > x.max) return []

			return [{ date, description, left: `${x.getPixelForValue(timestamp)}px` }]
		})
})
</script>

<template>
	<div class="graph-wrapper-cjs line">
		<Line :data :plugins :options />
		<div v-if="updateMarkers.length" class="algorithm-markers" :style="{ top: `${chartLayout.bottom}px` }">
			<template v-for="{ date, description, left } in updateMarkers" :key="date">
				<span
					class="algorithm-marker"
					:style="{ left }"
					:aria-label="`PHP monitoring update on ${date}. ${description}`"
				></span>
				<div class="algorithm-marker-detail" :style="{ '--marker-left': left }" aria-hidden="true">
					<strong>PHP monitoring update</strong>
					<time :datetime="date">{{ date }}</time>
					<div>{{ description }}</div>
				</div>
			</template>
		</div>
	</div>
</template>