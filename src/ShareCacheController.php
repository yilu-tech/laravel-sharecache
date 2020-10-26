<?php


namespace YiluTech\ShareCache;

use Illuminate\Http\Request;

class ShareCacheController
{
    public function restore(ShareCacheServiceManager $shareCacheManager, Request $request)
    {
        try {
            return $shareCacheManager->service()->object($request->input('name'))->restore($request->input('key'));
        } catch (ShareCacheException $exception) {
            return response(['message' => $exception->getMessage()], 501);
        }
    }
}
