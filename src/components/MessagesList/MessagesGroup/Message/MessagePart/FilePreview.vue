<!--
  - @copyright Copyright (c) 2019 Joas Schilling <coding@schilljs.com>
  - @copyright Copyright (c) 2020 Marco Ambrosini <marcoambrosini@pm.me>
  -
  - @author Joas Schilling <coding@schilljs.com>
  - @author Marco Ambrosini <marcoambrosini@pm.me>
  -
  - @license GNU AGPL version 3 or any later version
  -
  - This program is free software: you can redistribute it and/or modify
  - it under the terms of the GNU Affero General Public License as
  - published by the Free Software Foundation, either version 3 of the
  - License, or (at your option) any later version.
  -
  - This program is distributed in the hope that it will be useful,
  - but WITHOUT ANY WARRANTY; without even the implied warranty of
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
-->

<template>
	<file-preview v-bind="filePreview"
		:tabindex="wrapperTabIndex"
		class="file-preview"
		:class="{ 'file-preview--viewer-available': isViewerAvailable,
			'file-preview--upload-editor': isUploadEditor,
			'file-preview--shared-items-grid': isSharedItemsTab && !rowLayout,
			'file-preview--row-layout': rowLayout }"
		@click.exact="handleClick"
		@keydown.enter="handleClick">
		<div v-if="!isLoading"
			class="image-container"
			:class="{'playable': isPlayable}">
			<span v-if="isPlayable && !smallPreview" class="play-video-button">
				<PlayCircleOutline :size="48"
					decorative
					fill-color="#ffffff"
					title="" />
			</span>
			<img v-if="!failed"
				v-tooltip="previewTooltip"
				:class="previewImageClass"
				class="file-preview__image"
				alt=""
				:src="previewUrl">
			<img v-else
				:class="previewImageClass"
				alt=""
				:src="defaultIconUrl">
		</div>
		<span v-if="isLoading"
			v-tooltip="previewTooltip"
			class="preview loading" />
		<Button v-if="isUploadEditor"
			class="remove-file"
			tabindex="1"
			type="primary"
			:aria-label="removeAriaLabel"
			@click="$emit('remove-file', id)">
			<template #icon>
				<Close decorative
					title="" />
			</template>
		</Button>
		<ProgressBar v-if="isTemporaryUpload && !isUploadEditor" :value="uploadProgress" />
		<div v-if="shouldShowFileDetail" class="name-container">
			{{ fileDetail }}
		</div>
	</file-preview>
</template>

<script>
import { generateUrl, imagePath, generateRemoteUrl } from '@nextcloud/router'
import ProgressBar from '@nextcloud/vue/dist/Components/ProgressBar'
import Tooltip from '@nextcloud/vue/dist/Directives/Tooltip'
import Close from 'vue-material-design-icons/Close'
import PlayCircleOutline from 'vue-material-design-icons/PlayCircleOutline'
import { getCapabilities } from '@nextcloud/capabilities'
import { encodePath } from '@nextcloud/paths'
import AudioPlayer from './AudioPlayer.vue'
import Button from '@nextcloud/vue/dist/Components/Button'

const PREVIEW_TYPE = {
	TEMPORARY: 0,
	MIME_ICON: 1,
	DIRECT: 2,
	PREVIEW: 3,
}

export default {
	name: 'FilePreview',

	components: {
		ProgressBar,
		Close,
		PlayCircleOutline,
		Button,
	},

	directives: {
		tooltip: Tooltip,
	},

	props: {
		/**
		 * File id
		 */
		id: {
			type: String,
			required: true,
		},
		/**
		 * File name
		 */
		name: {
			type: String,
			required: true,
		},
		/**
		 * File path relative to the user's home storage,
		 * or link share root, includes the file name.
		 */
		path: {
			type: String,
			default: '',
		},
		/**
		 * File size in bytes
		 */
		size: {
			type: Number,
			default: -1,
		},
		/**
		 * Download link
		 */
		link: {
			type: String,
			default: '',
		},
		/**
		 * Mime type
		 */
		mimetype: {
			type: String,
			default: '',
		},
		/**
		 * Whether a preview is available, string "yes" for yes
		 * otherwise the string "no"
		 */
		// FIXME: use booleans here
		previewAvailable: {
			type: String,
			default: 'no',
		},

		/**
		 * Whether to render a small preview to embed in replies
		 */
		smallPreview: {
			type: Boolean,
			default: false,
		},
		/**
		 * Upload id from the file upload store.
		 *
		 * In case this component is used to display a file that is being uploaded
		 * this parameter is used to access the file upload status in the store
		 */
		uploadId: {
			type: Number,
			default: null,
		},
		/**
		 * File upload index from the file upload store.
		 *
		 * In case this component is used to display a file that is being uploaded
		 * this parameter is used to access the file upload status in the store
		 */
		index: {
			type: String,
			default: '',
		},
		/**
		 * Whether the container is the upload editor.
		 * True if this component is used in the upload editor.
		 */
		// FIXME: file-preview should be encapsulated and not be aware of its surroundings
		isUploadEditor: {
			type: Boolean,
			default: false,
		},
		/**
		 * The link to the file for displaying it in the preview
		 */
		localUrl: {
			type: String,
			default: '',
		},

		isVoiceMessage: {
			type: Boolean,
			default: false,
		},

		rowLayout: {
			type: Boolean,
			default: false,
		},

		isSharedItemsTab: {
			type: Boolean,
			default: false,
		},
	},
	data() {
		return {
			isLoading: true,
			failed: false,
		}
	},
	computed: {
		shouldShowFileDetail() {
			if (this.isSharedItemsTab && !this.rowLayout) {
				return false
			}
			// display the file detail below the preview if the preview
			// is not easily recognizable, when:
			return (
				// the file is not an image
				!this.mimetype.startsWith('image/')
				// the image has no preview (ex: disabled on server)
				|| (this.previewAvailable !== 'yes' && !this.localUrl)
				// the preview failed loading
				|| this.failed
				// always show in upload editor
				|| this.isUploadEditor
			)
		},

		fileDetail() {
			return this.name
		},

		previewTooltip() {
			if (this.shouldShowFileDetail) {
				// no tooltip as the file name is already visible directly
				return null
			}
			return {
				content: this.name,
				delay: { show: 500 },
				placement: 'left',
			}
		},

		// This is used to decide which outer element type to use
		// a or div
		filePreview() {
			if (this.isUploadEditor || this.isTemporaryUpload) {
				return {
					is: 'div',
					tag: 'div',
				}
			} else if (this.isVoiceMessage) {
				return {
					is: AudioPlayer,
					name: this.name,
					path: this.path,
					link: this.link,
				}
			}
			return {
				is: 'a',
				tag: 'a',
				href: this.link,
				target: '_blank',
				rel: 'noopener noreferrer',
			}
		},

		defaultIconUrl() {
			return imagePath('core', 'filetypes/file')
		},

		previewImageClass() {
			let classes = ''
			if (this.smallPreview) {
				classes += 'preview-small '
			} else {
				classes += 'preview '
			}

			if (this.failed || this.previewType === PREVIEW_TYPE.MIME_ICON || this.rowLayout) {
				classes += 'mimeicon'
			}
			return classes
		},

		previewType() {
			if (this.hasTemporaryImageUrl) {
				return PREVIEW_TYPE.TEMPORARY
			}

			if (this.previewAvailable !== 'yes') {
				return PREVIEW_TYPE.MIME_ICON
			}
			const maxGifSize = getCapabilities()?.spreed?.config?.previews?.['max-gif-size'] || 3145728
			if (this.mimetype === 'image/gif' && this.size <= maxGifSize) {
				return PREVIEW_TYPE.DIRECT
			}

			return PREVIEW_TYPE.PREVIEW
		},

		previewUrl() {
			const userId = this.$store.getters.getUserId()

			if (this.previewType === PREVIEW_TYPE.TEMPORARY) {
				return this.localUrl
			}
			if (this.previewType === PREVIEW_TYPE.MIME_ICON || this.rowLayout) {
				return OC.MimeType.getIconUrl(this.mimetype)
			}
			// whether to embed/render the file directly
			if (this.previewType === PREVIEW_TYPE.DIRECT) {
				// return direct image
				if (userId === null) {
					// guest mode, use public link download URL
					return this.link + '/download/' + encodePath(this.name)
				} else {
					// use direct DAV URL
					return generateRemoteUrl(`dav/files/${userId}`) + encodePath(this.internalAbsolutePath)
				}
			}

			// use preview provider URL to render a smaller preview
			let previewSize = 384
			if (this.smallPreview) {
				previewSize = 32
			}
			previewSize = Math.ceil(previewSize * window.devicePixelRatio)
			if (userId === null) {
				// guest mode: grab token from the link URL
				// FIXME: use a cleaner way...
				const token = this.link.slice(this.link.lastIndexOf('/') + 1)
				return generateUrl('/apps/files_sharing/publicpreview/{token}?x=-1&y={height}&a=1', {
					token,
					height: previewSize,
				})
			} else {
				return generateUrl('/core/preview?fileId={fileId}&x=-1&y={height}&a=1', {
					fileId: this.id,
					height: previewSize,
				})
			}
		},

		isViewerAvailable() {
			if (!OCA.Viewer) {
				return false
			}

			const availableHandlers = OCA.Viewer.availableHandlers
			for (let i = 0; i < availableHandlers.length; i++) {
				if (availableHandlers[i]?.mimes?.includes(this.mimetype)) {
					return true
				}
			}

			return false
		},

		isPlayable() {
			// don't show play button for direct renders
			if (this.failed || !this.isViewerAvailable || this.previewType !== PREVIEW_TYPE.PREVIEW) {
				return false
			}

			// videos only display a preview, so always show a button if playable
			return this.mimetype === 'image/gif' || this.mimetype.startsWith('video/')
		},

		internalAbsolutePath() {
			if (this.path.startsWith('/')) {
				return this.path
			}

			return '/' + this.path
		},

		isTemporaryUpload() {
			return this.id.startsWith('temp') && this.index && this.uploadId
		},

		uploadProgress() {
			if (this.isTemporaryUpload) {
				if (this.$store.getters.uploadProgress(this.uploadId, this.index)) {
					return this.$store.getters.uploadProgress(this.uploadId, this.index)
				}
			}
			// likely never reached
			return 0
		},

		hasTemporaryImageUrl() {
			return this.mimetype.startsWith('image/') && this.localUrl
		},

		wrapperTabIndex() {
			return this.isUploadEditor ? '0' : ''
		},

		removeAriaLabel() {
			return t('spreed', 'Remove {fileName}', { fileName: this.name })
		},
	},

	mounted() {
		const img = new Image()
		img.onerror = () => {
			this.isLoading = false
			this.failed = true
		}
		img.onload = () => {
			this.isLoading = false
		}
		img.src = this.previewUrl
	},

	methods: {
		handleClick(event) {
			if (this.isUploadEditor) {
				this.$emit('remove-file', this.id)
				return
			}

			if (!this.isViewerAvailable) {
				// Regular event handling by opening the link.
				return
			}

			event.stopPropagation()
			event.preventDefault()

			// The Viewer expects a file to be set in the sidebar if the sidebar
			// is open.
			if (this.$store.getters.getSidebarStatus) {
				OCA.Files.Sidebar.state.file = this.internalAbsolutePath
			}

			OCA.Viewer.open({
				// Viewer expects an internal absolute path starting with "/".
				path: this.internalAbsolutePath,
				list: [
					{
						fileid: parseInt(this.id, 10),
						filename: this.internalAbsolutePath,
						basename: this.name,
						mime: this.mimetype,
						hasPreview: this.previewAvailable === 'yes',
					},
				],
			})

			// FIXME Remove this hack once it is possible to set the parent
			// element of the viewer.
			// By default the viewer is a sibling of the fullscreen element, so
			// it is not visible when in fullscreen mode. It is not possible to
			// specify the parent nor to know when the viewer was actually
			// opened, so for the time being it is reparented if needed shortly
			// after calling it.
			setTimeout(() => {
				if (this.$store.getters.isFullscreen()) {
					document.getElementById('content-vue').appendChild(document.getElementById('viewer'))
				}
			}, 1000)
		},
	},
}
</script>

<style lang="scss" scoped>
@import '../../../../../assets/variables';

.file-preview {
	position: relative;
	min-width: 0;
	width: 100%;
	/* The file preview can not be a block; otherwise it would fill the whole
	width of the container and the loading icon would not be centered on the
	image. */
	display: inline-block;

	border-radius: 16px;

	box-sizing: content-box !important;
	&:hover,
	&:focus {
		background-color: var(--color-background-hover);
		.remove-file {
			visibility: visible;
		}
	}

	&__image {
		object-fit: cover;
	}

	.loading {
		display: inline-block;
		width: 100%;
	}

	.mimeicon {
		min-height: 128px;
	}

	.mimeicon.preview-small {
		min-height: auto;
		height: 32px;
	}

	.preview {
		display: inline-block;
		border-radius: var(--border-radius);
		max-width: 100%;
		max-height: 384px;
	}
	.preview-small {
		display: inline-block;
		border-radius: var(--border-radius);
		max-width: 100%;
		max-height: 32px;
	}

	.image-container {
		display: flex;
		height: 100%;

		&.playable {
			.preview {
				transition: filter 250ms ease-in-out;
			}

			.play-video-button {
				position: absolute;
				height: 48px; /* for proper vertical centering */
				top: 50%;
				left: 50%;
				transform: translate(-50%, -50%);
				opacity: 0.8;
				z-index: 1;
				transition: opacity 250ms ease-in-out;
			}

			&:hover {
				.preview {
					filter: brightness(80%);
				}

				.play-video-button {
					opacity: 1;
				}
			}
		}
	}

	.name-container {
		font-weight: bold;
		width: 100%;
		overflow: hidden;
		white-space: nowrap;
		text-overflow: ellipsis;
	}

	&:not(.file-preview--viewer-available) {
		strong:after {
			content: ' ↗';
		}
	}
	&--upload-editor {
		max-width: 140px;
		max-height: 140px;
		padding: 12px 12px 24px 12px;
		margin: 10px;

		.preview {
			margin: auto;
			width: 128px;
			height: 128px;
		}
		.loading {
			width: 100%;
		}
	}

	&--row-layout {
		display: flex;
		align-items: center;
		height: 36px;
		border-radius: var(--border-radius);
		padding: 2px 4px;

		.image-container {
			height: 100%;
		}

		.name-container {
			padding: 0 4px;
		}

		.loading {
			width: 36px;
			height: 36px;
		}
	}

	&--shared-items-grid {
		aspect-ratio: 1;
		.preview {
			width: 100%;
			min-height: unset;
			height: 100%;
		}
	}
}

.remove-file {
	visibility: hidden;
	position: absolute;
	top: 8px;
	right: 8px;
}

</style>
