<?php


namespace YiluTech\ShareCache;

use Illuminate\Http\Request;

class ShareCacheController
{
    public function restore(ShareCacheServiceManager $shareCacheManager, Request $request)
    {
        try {
            $keys   = $request->input('keys');
            $object = $shareCacheManager->service()->object($request->input('name'));
            if (is_array($keys)) {
                return $object->restoreMany($keys);
            }
            if (is_null($keys)) {
                return $object->restore();
            }
            return $object->restore($keys);
        } catch (ShareCacheException $exception) {
            return response(['message' => $exception->getMessage()], 406);
        } catch (\Exception $exception) {
            return response(['message' => 'Parameter abnormal.'], 500);
        }
    }
}
