import React, {useState, useEffect, Fragment} from 'react'
import { connect } from 'react-redux'
import { TextArea, Icon } from '@neos-project/react-ui-components'
import axios from 'axios'

const mapStateToProps = (state) => {
	const documentNode = state.cr.nodes.documentNode || null
	return {
		node: documentNode
	}
}

const AiSeoEditor = (props) => {
	const { value, commit, node, options } = props
	const [localValue, setLocalValue] = useState(value || '')
	const [loading, setLoading] = useState(false)
	const csrfToken = document.querySelector('[data-csrf-token]').dataset.csrfToken
	console.log(csrfToken)
	// Sync local state with external value from Neos
	useEffect(() => {
		setLocalValue(value || '')
	}, [value])


	// Handle change using onChange (correct for this TextArea component)
	const handleChange = (newValue) => {
		setLocalValue(newValue)
		commit(newValue)
	}

	const generateSeoData = async () => {
		if (!csrfToken) {
			console.error('CSRF token not found. Cannot make request.')
			return
		}

		setLoading(true)
		try {
			const response = await axios.post('/neos/content-ai-assistant/generateseodata', {
				node: node,
				propertyName: options.propertyName
			}, {
				headers: {
					'Content-Type': 'application/json',
					'X-Flow-CsrfToken': csrfToken
				},
				withCredentials: true
			})
			const generatedValue = response.data[options.propertyName]
			setLocalValue(generatedValue)
			commit(generatedValue)
		} catch (error) {
			console.error('Error generating data:', error)
		} finally {
			setLoading(false)
		}
	}

	return (
		<div style={{display: 'flex', flexDirection: 'column', gap: '10px'}}>
			<TextArea
				value={localValue}
				onChange={handleChange}   // Correct usage for this TextArea component
				placeholder="Type your SEO content here..."
			/>
			{(!localValue || localValue.trim() === '') && (
				<div
					onClick={generateSeoData}
					style={{
						alignSelf: 'end',
						display: 'flex',
						fontSize: 'small',
						alignItems: 'center',
						cursor: 'pointer'
					}}
				>
					{loading ?
						<Fragment>
							Generating... <Icon icon="spinner" spin style={{ marginLeft: '5px' }} />
						</Fragment> : <Icon icon="magic" style={{ marginLeft: '5px' }} />
					}
				</div>
			)}
		</div>
	)
}

export default connect(mapStateToProps)(AiSeoEditor)
