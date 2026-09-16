import fs from 'node:fs/promises';
import path from 'node:path';
import { pathToFileURL } from 'node:url';
const ROOT='D:/xampp/htdocs/codex_lensify';
const SKILL_DIR='C:/Users/Asus tuf/.codex/plugins/cache/openai-primary-runtime/presentations/26.909.61513/skills/presentations';
const workspaceDir=ROOT;
const FINAL_PPTX=path.join(ROOT,'.lensify-deck-output','Lensify-Project-Showcase.pptx');
const candidatePath=path.join(ROOT,'.lensify-deck-build','lensify-showcase-draft.pptx');
const { finalizePresentation } = await import(pathToFileURL(path.join(SKILL_DIR,'container_tools/artifact_tool_utils.mjs')).href);
const stagingDir=path.join(ROOT,'.lensify-deck-build','.codex-finalizer');
await fs.mkdir(stagingDir,{recursive:true});
const result=await finalizePresentation({
  workspaceDir,
  candidatePath,
  finalPath:FINAL_PPTX,
  pythonExecutable:'C:/Users/Asus tuf/.cache/codex-runtimes/codex-primary-runtime/dependencies/python/python.exe',
  integrityValidatorPath:path.join(SKILL_DIR,'container_tools/inspect_presentation_package_integrity.py'),
  layoutValidatorPath:path.join(SKILL_DIR,'container_tools/inspect_presentation_layout_geometry.py'),
  layoutArgs:['--expected-slide-size-emu','12192000,6858000','--validate-bullet-geometry','--validate-heading-fit'],
  explicitTotalSlideCount:33,
  requiredNativeTableOwnerSlides:[],
  requiredNativeChartOwnerSlides:[],
  fontPolicy:{basis:'design',families:['Aptos']},
  verifyArtifactToolImport:true,
  receiptPath:path.join(stagingDir,'Lensify-Project-Showcase.validation.json'),
});
console.log(JSON.stringify(result));
