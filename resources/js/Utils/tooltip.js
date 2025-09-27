const tooltipsMap = new WeakMap()

export const tooltip = {
	mounted(el, binding) {
		if (!binding.value?.text && !binding.value) return

		const tooltipData = {
			touch: binding.value?.touch || binding.modifiers.touch,
			hoverable: binding.value?.hoverable || binding.modifiers.hoverable,
			width: binding.value?.width || 'normal',
			wrapper: null,
			destination: null,
			show: false,
			text: binding.value?.text || binding.value,
			tooltipEl: null,
			eventHandlers: {}
		}

		tooltipsMap.set(el, tooltipData)
		addEvents(el)
	},
	beforeUnmount(el) {
		const tooltipData = tooltipsMap.get(el)
		if (!tooltipData) return

		const handlers = tooltipData.eventHandlers
		if (handlers.handleEnter) el.removeEventListener('pointerenter', handlers.handleEnter)
		if (handlers.handleCancel) el.removeEventListener('pointercancel', handlers.handleCancel)
		if (handlers.handleLeave) el.removeEventListener('mouseleave', handlers.handleLeave)

		if (tooltipData.show) destroyTooltip(el)
		tooltipsMap.delete(el)
	},
	beforeUpdate(el, binding) {
		const tooltipData = tooltipsMap.get(el)
		if (!tooltipData || binding.oldValue === binding.value) return

		const newText = binding.value?.text || binding.value
		tooltipData.text = newText

		if (tooltipData.show) {
			if (newText) updateTooltipContent(el)
			else destroyTooltip(el)
		}
	}
}

function setDestination(el) {
	const tooltipData = tooltipsMap.get(el)
	if (!tooltipData) return

	const wrapper = el.closest('.overflowCont') ?? document.documentElement
	tooltipData.wrapper = wrapper
	tooltipData.destination = wrapper === document.documentElement ? document.body : wrapper
}

function addEvents(el) {
	const tooltipData = tooltipsMap.get(el)
	if (!tooltipData) return

	const handleEnterFunction = (e) => handleEnter(e, el)
	const handleCancelFunction = (e) => handleCancel(e, el)
	const handleLeaveFunction = (e) => handleLeave(e, el)

	tooltipData.eventHandlers = {
		handleEnter: handleEnterFunction,
		handleCancel: handleCancelFunction,
		handleLeave: handleLeaveFunction
	}

	el.addEventListener('pointerenter', handleEnterFunction, {passive: true})
	el.addEventListener('pointercancel', handleCancelFunction, {passive: true})
	el.addEventListener('mouseleave', handleLeaveFunction, {passive: true})
}

function createTooltipEl(el) {
	const tooltipData = tooltipsMap.get(el)
	if (!tooltipData?.text) return

	const element = Object.assign(document.createElement('div'), {
		className: `tooltip tooltip-${tooltipData.width}${tooltipData.hoverable ? ' isHoverable' : ''}`,
		innerHTML: tooltipData.text
	})

	if (tooltipData.hoverable) {
		const handleLeaveFunction = (e) => handleLeave(e, el)
		element.addEventListener('mouseleave', handleLeaveFunction, {passive: true})
	}

	return element
}

function updateTooltipContent(el) {
	const tooltipData = tooltipsMap.get(el)
	if (!tooltipData?.tooltipEl) return

	if (!tooltipData.text) return destroyTooltip(el)

	tooltipData.tooltipEl.innerHTML = tooltipData.text
}

function handleEnter(e, el) {
	const tooltipData = tooltipsMap.get(el)
	if (!tooltipData?.text) return

	const isTouch = e.pointerType === 'touch'
	if (!tooltipData.touch && isTouch) return

	if (isTouch && tooltipData.show) return destroyTooltip(el)

	if (tooltipData.show) return

	if (!tooltipData.destination) setDestination(el)

	const tooltipEl = createTooltipEl(el)
	if (!tooltipEl) return

	setCoords(tooltipEl, el, tooltipData.wrapper)

	tooltipData.tooltipEl = tooltipEl
	tooltipData.destination.append(tooltipEl)
	tooltipData.show = true
}

function handleCancel(e, el) {
	const tooltipData = tooltipsMap.get(el)
	if (!tooltipData?.text) return

	if (tooltipData.touch && e.pointerType === 'touch' && tooltipData.show) return destroyTooltip(el)
}

function handleLeave(e, el) {
	const tooltipData = tooltipsMap.get(el)
	if (!tooltipData?.show) return

	const relatedTarget = e.relatedTarget
	if (!relatedTarget) return destroyTooltip(el)

	const isLeavingToTooltip = tooltipData.tooltipEl?.contains(relatedTarget)
	const isLeavingToTrigger = el.contains(relatedTarget)

	if (!isLeavingToTooltip && !isLeavingToTrigger) destroyTooltip(el)
}

function destroyTooltip(el) {
	const tooltipData = tooltipsMap.get(el)
	if (!tooltipData?.tooltipEl) return

	tooltipData.tooltipEl.remove()
	tooltipData.tooltipEl = null
	tooltipData.show = false
}

function setCoords(tooltipEl, tooltipCont, tooltipWrapper) {
	requestAnimationFrame(() => {
		const { left, top, width, height } = tooltipCont.getBoundingClientRect()

		const styles = {
			'--tt-w': `${width}px`,
			'--tt-h': `${height}px`,
			'--tt-l': `${left}px`,
			'--tt-t': `${top}px`,
			'--tt-ww': `${tooltipWrapper.clientWidth}px`,
			'--tt-ofTop': `${top + tooltipWrapper.scrollTop}px`
		}

		Object.entries(styles).forEach(([prop, value]) => {
			tooltipEl.style.setProperty(prop, value)
		})
	})
}