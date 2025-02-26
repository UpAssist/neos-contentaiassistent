import manifest from '@neos-project/neos-ui-extensibility';
import AiSeoEditor from './AiSeoEditor';

// Register the custom editor with Neos UI
manifest('UpAssist.Neos.ContentAiAssistent:AiSeoEditor', {}, globalRegistry => {
	const editorsRegistry = globalRegistry.get('inspector').get('editors');
	editorsRegistry.set('UpAssist.Neos.ContentAiAssistent/AiSeoEditor', {
		component: AiSeoEditor
	});
});
