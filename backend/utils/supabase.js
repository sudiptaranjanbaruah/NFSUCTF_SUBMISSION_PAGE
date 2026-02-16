const { createClient } = require('@supabase/supabase-js');
const logger = require('./logger');

let supabase = null;

function getSupabase() {
    if (!supabase) {
        const url = process.env.SUPABASE_URL;
        const key = process.env.SUPABASE_SERVICE_ROLE_KEY;
        if (!url || !key) {
            logger.warn('Supabase credentials not configured — file storage disabled');
            return null;
        }
        supabase = createClient(url, key);
    }
    return supabase;
}

const BUCKET_NAME = 'challenge-files';

/**
 * Upload a file to Supabase Storage
 */
async function uploadFile(filePath, fileBuffer, contentType) {
    const sb = getSupabase();
    if (!sb) throw new Error('Supabase not configured');

    const { data, error } = await sb.storage
        .from(BUCKET_NAME)
        .upload(filePath, fileBuffer, {
            contentType,
            upsert: true,
        });

    if (error) {
        logger.error({ error }, 'Supabase upload error');
        throw error;
    }

    const { data: urlData } = sb.storage.from(BUCKET_NAME).getPublicUrl(filePath);
    return urlData.publicUrl;
}

/**
 * Delete a file from Supabase Storage
 */
async function deleteFile(filePath) {
    const sb = getSupabase();
    if (!sb) throw new Error('Supabase not configured');

    const { error } = await sb.storage.from(BUCKET_NAME).remove([filePath]);
    if (error) {
        logger.error({ error }, 'Supabase delete error');
        throw error;
    }
}

module.exports = { getSupabase, uploadFile, deleteFile, BUCKET_NAME };
