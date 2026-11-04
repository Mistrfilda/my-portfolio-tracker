import fs from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const [schemaPath, dataPath, ...unexpectedArguments] = process.argv.slice(2);
if (!schemaPath || !dataPath || unexpectedArguments.length > 0) {
  console.error('Usage: node validate-stock-json.mjs SCHEMA DATA');
  process.exit(2);
}

function readJson(filePath) {
  try {
    return JSON.parse(fs.readFileSync(filePath, 'utf8'));
  } catch (error) {
    const message = error instanceof Error ? error.message : String(error);
    console.error(`Could not read JSON from ${filePath}: ${message}`);
    process.exit(2);
  }
}

const schema = readJson(schemaPath);
const data = readJson(dataPath);
const errors = [];

function valueType(value) {
  if (value === null) return 'null';
  if (Array.isArray(value)) return 'array';
  if (Number.isInteger(value)) return 'integer';
  return typeof value;
}

function acceptsType(value, expected) {
  if (expected === 'number') return typeof value === 'number' && Number.isFinite(value);
  if (expected === 'integer') return Number.isInteger(value);
  if (expected === 'object') return value !== null && typeof value === 'object' && !Array.isArray(value);
  if (expected === 'array') return Array.isArray(value);
  if (expected === 'null') return value === null;
  return typeof value === expected;
}

function validDate(value) {
  if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) return false;

  const parsed = new Date(`${value}T00:00:00Z`);
  return !Number.isNaN(parsed.getTime()) && parsed.toISOString().slice(0, 10) === value;
}

function validDateTime(value) {
  if (!/^\d{4}-\d{2}-\d{2}[Tt]\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:[Zz]|[+-]\d{2}:\d{2})$/.test(value)) {
    return false;
  }

  return !Number.isNaN(Date.parse(value));
}

function validate(nodeSchema, value, pointer = '$') {
  if (nodeSchema.type !== undefined) {
    const expected = Array.isArray(nodeSchema.type) ? nodeSchema.type : [nodeSchema.type];
    if (!expected.some((type) => acceptsType(value, type))) {
      errors.push(`${pointer}: expected ${expected.join('|')}, got ${valueType(value)}`);
      return;
    }
  }

  if (
    Array.isArray(nodeSchema.enum)
    && !nodeSchema.enum.some((item) => JSON.stringify(item) === JSON.stringify(value))
  ) {
    errors.push(`${pointer}: value is not in enum`);
  }

  if (typeof value === 'string') {
    if (nodeSchema.minLength !== undefined && [...value].length < nodeSchema.minLength) {
      errors.push(`${pointer}: string shorter than ${nodeSchema.minLength}`);
    }
    if (
      nodeSchema.format === 'uuid'
      && !/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i.test(value)
    ) {
      errors.push(`${pointer}: invalid UUID`);
    }
    if (nodeSchema.format === 'date' && !validDate(value)) {
      errors.push(`${pointer}: invalid date`);
    }
    if (nodeSchema.format === 'date-time' && !validDateTime(value)) {
      errors.push(`${pointer}: invalid date-time`);
    }
  }

  if (typeof value === 'number') {
    if (nodeSchema.minimum !== undefined && value < nodeSchema.minimum) {
      errors.push(`${pointer}: below minimum ${nodeSchema.minimum}`);
    }
    if (nodeSchema.maximum !== undefined && value > nodeSchema.maximum) {
      errors.push(`${pointer}: above maximum ${nodeSchema.maximum}`);
    }
  }

  if (Array.isArray(value)) {
    if (nodeSchema.minItems !== undefined && value.length < nodeSchema.minItems) {
      errors.push(`${pointer}: fewer than ${nodeSchema.minItems} items`);
    }
    if (nodeSchema.maxItems !== undefined && value.length > nodeSchema.maxItems) {
      errors.push(`${pointer}: more than ${nodeSchema.maxItems} items`);
    }
    if (nodeSchema.items !== undefined) {
      value.forEach((item, index) => validate(nodeSchema.items, item, `${pointer}[${index}]`));
    }
  }

  if (value !== null && typeof value === 'object' && !Array.isArray(value)) {
    const keys = Object.keys(value);
    if (nodeSchema.minProperties !== undefined && keys.length < nodeSchema.minProperties) {
      errors.push(`${pointer}: fewer than ${nodeSchema.minProperties} properties`);
    }
    if (nodeSchema.maxProperties !== undefined && keys.length > nodeSchema.maxProperties) {
      errors.push(`${pointer}: more than ${nodeSchema.maxProperties} properties`);
    }
    for (const required of nodeSchema.required ?? []) {
      if (!Object.hasOwn(value, required)) {
        errors.push(`${pointer}: missing required property ${required}`);
      }
    }
    for (const key of keys) {
      if (Object.hasOwn(nodeSchema.properties ?? {}, key)) {
        validate(nodeSchema.properties[key], value[key], `${pointer}.${key}`);
      } else if (nodeSchema.additionalProperties === false) {
        errors.push(`${pointer}: unexpected property ${key}`);
      }
    }
  }
}

function validateCompany(item, expected, pointer) {
	for (const key of ['stockAssetId', 'stockAssetName', 'stockAssetTicker']) {
		if ((item[key] ?? null) !== (expected[key] ?? null)) {
			errors.push(`${pointer}.${key}: must match the immutable input`);
		}
	}

	const valuation = item.valuation;
	const values = ['fairValueLow', 'fairValueBase', 'fairValueHigh', 'currency', 'method']
		.map((key) => valuation[key]);
	const nonNullCount = values.filter((value) => value !== null).length;
	if (nonNullCount !== 0 && nonNullCount !== values.length) {
		errors.push(`${pointer}.valuation: price range, currency, and method must be all null or all present`);
		return;
	}

	if (nonNullCount === 0) {
		if (valuation.assessment !== 'uncertain') {
			errors.push(`${pointer}.valuation.assessment: must be uncertain when no fair value is available`);
		}
		return;
	}

	const [low, base, high, currency] = values;
	if (low <= 0 || base <= 0 || high <= 0 || low > base || base > high) {
		errors.push(`${pointer}.valuation: must satisfy 0 < low <= base <= high`);
	}
	if (typeof expected.currency === 'string' && currency !== expected.currency) {
		errors.push(`${pointer}.valuation.currency: must equal ${expected.currency}`);
	}
	if (item.dataQuality.status === 'insufficient') {
		errors.push(`${pointer}.valuation: value fields must be null when dataQuality.status is insufficient`);
	}
}

function validateBusinessRules() {
	const bundleDirectory = dirname(fileURLToPath(import.meta.url));
	const manifest = readJson(join(bundleDirectory, 'manifest.json'));
	const fullResult = Object.hasOwn(schema.properties ?? {}, 'schemaVersion');
	if (fullResult) {
		for (const key of ['schemaVersion', 'runId', 'analysisAsOf']) {
			if (data[key] !== manifest[key]) errors.push(`$.${key}: must match manifest.json`);
		}
	}

	for (const [rootKey, prefix, idsKey] of [
		['portfolioAnalysis', 'portfolio', 'portfolioStockAssetIds'],
		['watchlistAnalysis', 'watchlist', 'watchlistStockAssetIds'],
		['simpleWatchlistAnalysis', 'simple-watchlist', 'simpleWatchlistIds'],
	]) {
		if (!Object.hasOwn(data, rootKey)) continue;
		if (!manifest.requiredSections.includes(rootKey)) {
			errors.push(`$.${rootKey}: section is outside the requested scope`);
			continue;
		}

		const expectedById = new Map((manifest[idsKey] ?? []).map((id, index) => {
			const inputPath = join(bundleDirectory, 'input', `${prefix}-${String(index + 1).padStart(3, '0')}-${id}.json`);
			return [id, readJson(inputPath)];
		}));
		const seenIds = new Set();
		data[rootKey].forEach((item, index) => {
			const pointer = `$.${rootKey}[${index}]`;
			const expected = expectedById.get(item.stockAssetId);
			if (!expected) {
				errors.push(`${pointer}: unexpected stockAssetId`);
				return;
			}
			if (seenIds.has(item.stockAssetId)) {
				errors.push(`${pointer}: duplicate stockAssetId ${item.stockAssetId}`);
				return;
			}
			seenIds.add(item.stockAssetId);
			validateCompany(item, expected, pointer);
		});
		if (fullResult) {
			for (const id of expectedById.keys()) {
				if (!seenIds.has(id)) errors.push(`$.${rootKey}: missing stockAssetId ${id}`);
			}
		}
	}

	if (Object.hasOwn(data, 'stockAnalysis')) {
		if (!manifest.requiredSections.includes('stockAnalysis')) {
			errors.push('$.stockAnalysis: section is outside the requested scope');
		} else {
			validateCompany(data.stockAnalysis, readJson(join(bundleDirectory, 'input', 'stock.json')), '$.stockAnalysis');
		}
	}
}

validate(schema, data);
if (errors.length === 0) validateBusinessRules();

if (errors.length > 0) {
  for (const error of errors) console.error(error);
  console.error(`INVALID (${errors.length} error${errors.length === 1 ? '' : 's'})`);
  process.exit(1);
}

console.log(`VALID ${dataPath}`);
